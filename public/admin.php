<?php
header('Content-Type: text/html; charset=utf-8');
/**
 * Painel Administrativo KMKZ IPTV
 * Sistema completo de administração integrado com API
 */
require_once __DIR__ . '/../includes/api.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$authResponse = apiCheckAuth();
$user = null;

if (isApiResponseValid($authResponse) && ($authResponse['success'] ?? false) && ($authResponse['data']['logged_in'] ?? false)) {
    $user = $authResponse['data']['user'] ?? null;
} elseif (isset($_SESSION['user_logged_in'], $_SESSION['user_data']) && $_SESSION['user_logged_in']) {
    $user = $_SESSION['user_data'];
}

if (!$user) {
    header('Location: login.php');
    exit;
}

$role = $user['role'] ?? ($user['user_type'] ?? 'user');
if ($role !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

if (isset($user['id'])) {
    $_SESSION['user_id'] = $user['id'];
}
$_SESSION['user_type'] = 'admin';
if (isset($user['name'])) {
    $_SESSION['user_name'] = $user['name'];
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$path = strtok($_SERVER['REQUEST_URI'] ?? '/admin.php', '?');
$canonicalUrl = $scheme . '://' . $host . $path;
$ga4Id = getenv('GA4_MEASUREMENT_ID') ?: '';
$metaPixelId = getenv('META_PIXEL_ID') ?: '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - KMKZ IPTV</title>
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="theme-color" content="#020617">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- SweetAlert2 -->
    <script defer src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Custom Style -->
    <link href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css') ?: time(); ?>" rel="stylesheet">
    <link rel="icon" href="assets/images/Logo.png">
    
    <style>
        :root {
            --sidebar-width: 280px;
        }
        
        body {
            background: var(--bg-dark);
            background-image: 
                radial-gradient(at 0% 0%, rgba(147, 51, 234, 0.1) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(236, 72, 153, 0.1) 0px, transparent 50%);
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 4px 0 15px rgba(0,0,0,0.1);
        }
        
        .sidebar.collapsed {
            width: 80px;
        }
        
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        
        .sidebar-header .logo {
            height: 40px;
            transition: all 0.3s ease;
        }
        
        .sidebar.collapsed .sidebar-header .logo {
            height: 30px;
        }
        
        .sidebar-nav {
            padding: 1rem 0;
        }
        
        .nav-item {
            margin: 0.25rem 0;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 0;
        }
        
        .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(5px);
        }
        
        .nav-link.active {
            background: linear-gradient(135deg, var(--purple-primary), var(--purple-hover));
            color: white;
            box-shadow: 0 4px 15px rgba(147, 51, 234, 0.3);
        }
        
        .nav-link i {
            width: 20px;
            margin-right: 0.75rem;
            text-align: center;
        }
        
        .sidebar.collapsed .nav-link span {
            display: none;
        }
        
        .sidebar.collapsed .nav-link {
            justify-content: center;
            padding: 0.75rem;
        }
        
        .sidebar.collapsed .nav-link i {
            margin-right: 0;
        }
        
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            min-height: 100vh;
            transition: all 0.3s ease;
        }
        
        .main-content.expanded {
            margin-left: 80px;
        }
        
        .top-navbar {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
        }
        
        .content-area {
            padding: 2rem;
        }
        
        .section {
            display: none;
        }
        
        .section.active {
            display: block;
        }
        
        .dashboard-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            color: white;
            transition: all 0.3s ease;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .stat-card {
            background: linear-gradient(135deg, rgba(147, 51, 234, 0.1), rgba(236, 72, 153, 0.1));
            border: 1px solid rgba(147, 51, 234, 0.3);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            color: white;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
        }
        
        /* Estilos para modais modernos */
        .modern-form .form-control:focus,
        .modern-form .form-select:focus {
            background: rgba(255,255,255,0.15) !important;
            border-color: var(--purple-primary) !important;
            box-shadow: 0 0 0 0.2rem rgba(147, 51, 234, 0.25) !important;
            color: white !important;
        }
        
        .modern-form .form-control:hover,
        .modern-form .form-select:hover {
            background: rgba(255,255,255,0.12) !important;
            border-color: rgba(255,255,255,0.3) !important;
        }
        
        .modern-form .form-control::placeholder {
            color: rgba(255,255,255,0.6) !important;
        }
        
        .modern-form .form-label i {
            font-size: 1rem;
            width: 1.2rem;
            text-align: center;
        }
        
        /* Customização do SweetAlert2 */
        .swal2-popup {
            background: rgba(26, 26, 46, 0.95) !important;
            backdrop-filter: blur(20px) !important;
            border: 1px solid rgba(255,255,255,0.1) !important;
            border-radius: 20px !important;
        }
        
        .swal2-title {
            color: white !important;
            font-weight: 600 !important;
        }
        
        .swal2-confirm {
            background: linear-gradient(135deg, var(--purple-primary), var(--purple-hover)) !important;
            border: none !important;
            border-radius: 10px !important;
            padding: 0.75rem 1.5rem !important;
            font-weight: 600 !important;
            transition: all 0.3s ease !important;
        }
        
        .swal2-confirm:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 25px rgba(147, 51, 234, 0.3) !important;
        }
        
        .swal2-cancel {
            background: rgba(255,255,255,0.1) !important;
            border: 1px solid rgba(255,255,255,0.2) !important;
            color: white !important;
            border-radius: 10px !important;
            padding: 0.75rem 1.5rem !important;
            font-weight: 600 !important;
            transition: all 0.3s ease !important;
        }
        
        .swal2-cancel:hover {
            background: rgba(255,255,255,0.15) !important;
            border-color: rgba(255,255,255,0.3) !important;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, var(--purple-primary), var(--pink-accent));
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .stat-card:hover::before {
            opacity: 0.1;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(147, 51, 234, 0.3);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--purple-primary), var(--pink-accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }
        
        .stat-label {
            color: rgba(255,255,255,0.7);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
            z-index: 1;
        }
        
        .toggle-sidebar {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .toggle-sidebar:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--purple-primary), var(--pink-accent));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .data-table {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 15px;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        
        .table-header {
            background: linear-gradient(135deg, var(--purple-primary), var(--purple-hover));
            color: white;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table {
            color: white;
            margin-bottom: 0;
        }
        
        .table th {
            background: rgba(255,255,255,0.1);
            border: none;
            color: white;
            font-weight: 600;
        }
        
        .table td {
            border: none;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .table tbody tr:hover {
            background: rgba(255,255,255,0.05);
        }
        
        .btn-custom {
            border-radius: 25px;
            padding: 0.5rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
        }
        
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--purple-primary), var(--purple-hover));
            border: none;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            border: none;
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            border: none;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            border: none;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 1rem;
        }
        
        .loading {
            text-align: center;
            padding: 3rem;
        }
        
        .spinner-border {
            color: var(--purple-primary);
        }
        
        .form-control, .form-select {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
        }
        
        .form-control:focus, .form-select:focus {
            background: rgba(255,255,255,0.15);
            border-color: var(--purple-primary);
            box-shadow: 0 0 0 0.2rem rgba(147, 51, 234, 0.25);
            color: white;
        }
        
        .form-control::placeholder {
            color: rgba(255,255,255,0.5);
        }
        
        .form-label {
            color: rgba(255,255,255,0.9);
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body data-ga4-id="<?php echo htmlspecialchars($ga4Id, ENT_QUOTES, 'UTF-8'); ?>" data-meta-pixel-id="<?php echo htmlspecialchars($metaPixelId, ENT_QUOTES, 'UTF-8'); ?>">
    <a class="skip-link" href="#mainContent">Pular para o conteúdo</a>
    <div class="dashboard-container" id="mainContent">
        <!-- Sidebar -->
        <div class="sidebar admin-sidebar-glass" id="sidebar">
            <div class="sidebar-header">
                <img src="assets/images/Logo.png" alt="KMKZ IPTV" width="147" height="80" class="logo" decoding="async" fetchpriority="high">
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-item">
                    <a href="#dashboard" class="nav-link admin-nav-link active" data-section="dashboard">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#users" class="nav-link admin-nav-link" data-section="users">
                        <i class="fas fa-users"></i>
                        <span>Usuários</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#subscriptions" class="nav-link admin-nav-link" data-section="subscriptions">
                        <i class="fas fa-calendar-check"></i>
                        <span>Assinaturas</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#payments" class="nav-link admin-nav-link" data-section="payments">
                        <i class="fas fa-credit-card"></i>
                        <span>Pagamentos</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#plans" class="nav-link admin-nav-link" data-section="plans">
                        <i class="fas fa-box"></i>
                        <span>Planos</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#rewards" class="nav-link admin-nav-link" data-section="rewards">
                        <i class="fas fa-gift"></i>
                        <span>Recompensas</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#points" class="nav-link admin-nav-link" data-section="points">
                        <i class="fas fa-star"></i>
                        <span>Sistema de Pontos</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#settings" class="nav-link admin-nav-link" data-section="settings">
                        <i class="fas fa-cog"></i>
                        <span>Configurações</span>
                    </a>
                </div>
                <div class="nav-item mt-auto">
                    <a href="logout.php" class="nav-link admin-nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Sair</span>
                    </a>
                </div>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content" id="mainContent">
            <!-- Top Navbar -->
            <div class="top-navbar admin-top-navbar">
                <div class="d-flex align-items-center">
                    <button class="toggle-sidebar" id="toggleSidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h4 class="mb-0 ms-3" id="pageTitle">Dashboard</h4>
                </div>
                
                <div class="user-profile">
                    <div class="user-avatar">
                        <?php echo mb_strtoupper(mb_substr($_SESSION['user_name'], 0, 1, 'UTF-8'), 'UTF-8'); ?>
                    </div>
                    <div>
                        <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                        <small class="text-muted">Administrador</small>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <!-- Dashboard Section -->
                <div id="dashboard" class="section active">
                    <!-- Stats Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="admin-card-stat">
                                <div class="icon-box">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="stat-number" id="totalUsers">-</div>
                                <div class="stat-label">Total de Usuários</div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="admin-card-stat">
                                <div class="icon-box">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div class="stat-number" id="activeSubscriptions">-</div>
                                <div class="stat-label">Assinaturas Ativas</div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="admin-card-stat">
                                <div class="icon-box">
                                    <i class="fas fa-dollar-sign"></i>
                                </div>
                                <div class="stat-number" id="monthlyRevenue">-</div>
                                <div class="stat-label">Receita Mensal</div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="admin-card-stat">
                                <div class="icon-box">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="stat-number" id="pendingPayments">-</div>
                                <div class="stat-label">Pagamentos Pendentes</div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts and Activity -->
                    <div class="row">
                        <div class="col-md-8">
                            <div class="glass-panel p-4 mb-4">
                                <h5 class="mb-4 text-gradient">Receita dos Últimos 6 Meses</h5>
                                <div class="chart-container">
                                    <canvas id="revenueChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="glass-panel p-4 mb-4">
                                <h5 class="mb-4 text-gradient">Atividades Recentes</h5>
                                <div id="recentActivity">
                                    <div class="loading">
                                        <div class="spinner-border" role="status"></div>
                                        <div class="mt-2">Carregando...</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Users Section -->
                <div id="users" class="section">
                    <div class="admin-table-glass mb-4">
                        <div class="table-header d-flex justify-content-between align-items-center p-4">
                            <h5 class="mb-0 text-gradient">Gerenciar Usuários</h5>
                            <button class="btn btn-primary btn-custom" onclick="showCreateUserModal()">
                                <i class="fas fa-plus"></i> Novo Usuário
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>Email</th>
                                        <th>Tipo</th>
                                        <th>Status</th>
                                        <th>Cadastro</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="usersTableBody">
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <div class="spinner-border" role="status"></div>
                                            <div class="mt-2">Carregando usuários...</div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Subscriptions Section -->
                <div id="subscriptions" class="section">
                    <div class="admin-table-glass mb-4">
                        <div class="table-header d-flex justify-content-between align-items-center p-4">
                            <h5 class="mb-0 text-gradient">Gerenciar Assinaturas</h5>
                            <button class="btn btn-primary btn-custom" onclick="showCreateSubscriptionModal()">
                                <i class="fas fa-plus"></i> Nova Assinatura
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Usuário</th>
                                        <th>Plano</th>
                                        <th>Status</th>
                                        <th>Início</th>
                                        <th>Fim</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="subscriptionsTableBody">
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <div class="spinner-border" role="status"></div>
                                            <div class="mt-2">Carregando assinaturas...</div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Payments Section -->
                <div id="payments" class="section">
                    <div class="admin-table-glass mb-4">
                        <div class="table-header d-flex justify-content-between align-items-center p-4">
                            <h5 class="mb-0 text-gradient">Gerenciar Pagamentos</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Usuário</th>
                                        <th>Valor</th>
                                        <th>Método</th>
                                        <th>Status</th>
                                        <th>Data</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="paymentsTableBody">
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <div class="spinner-border" role="status"></div>
                                            <div class="mt-2">Carregando pagamentos...</div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Plans Section -->
                <div id="plans" class="section">
                    <div class="admin-table-glass mb-4">
                        <div class="table-header d-flex justify-content-between align-items-center p-4">
                            <h5 class="mb-0 text-gradient">Gerenciar Planos</h5>
                            <button class="btn btn-primary btn-custom" onclick="showCreatePlanModal()">
                                <i class="fas fa-plus"></i> Novo Plano
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>Duração</th>
                                        <th>Preço</th>
                                        <th>Desconto</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="plansTableBody">
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <div class="spinner-border" role="status"></div>
                                            <div class="mt-2">Carregando planos...</div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Rewards Section -->
                <div id="rewards" class="section">
                    <div class="admin-table-glass mb-4">
                        <div class="table-header d-flex justify-content-between align-items-center p-4">
                            <h5 class="mb-0 text-gradient">Sistema de Recompensas</h5>
                            <button class="btn btn-primary btn-custom" onclick="showCreateRewardModal()">
                                <i class="fas fa-plus"></i> Nova Recompensa
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>Pontos</th>
                                        <th>Estoque</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="rewardsTableBody">
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <div class="spinner-border" role="status"></div>
                                            <div class="mt-2">Carregando recompensas...</div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Points Section -->
                <div id="points" class="section">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="dashboard-card">
                                <h5 class="mb-3">Conceder Pontos</h5>
                                <form id="awardPointsForm">
                                    <div class="mb-3">
                                        <label class="form-label">Usuário</label>
                                        <select class="form-select" name="user_id" required>
                                            <option value="">Selecione um usuário</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Pontos</label>
                                        <input type="number" class="form-control" name="points" min="1" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Motivo</label>
                                        <input type="text" class="form-control" name="reason" placeholder="Ex: Bônus especial">
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-custom">
                                        <i class="fas fa-star"></i> Conceder Pontos
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="dashboard-card">
                                <h5 class="mb-3">Histórico de Pontos</h5>
                                <div id="pointsHistory">
                                    <div class="loading">
                                        <div class="spinner-border" role="status"></div>
                                        <div class="mt-2">Carregando histórico...</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Settings Section -->
                <div id="settings" class="section">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="dashboard-card">
                                <h5 class="mb-3">Configurações do Sistema</h5>
                                <form id="settingsForm">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Nome do Site</label>
                                                <input type="text" class="form-control" name="site_name" value="KMKZ IPTV">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Email de Contato</label>
                                                <input type="email" class="form-control" name="contact_email" value="contato@kmkziptv.com">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Pontos por Login Diário</label>
                                                <input type="number" class="form-control" name="daily_login_points" value="10">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Pontos por Indicação</label>
                                                <input type="number" class="form-control" name="referral_points" value="100">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Pontos por Assinatura</label>
                                                <input type="number" class="form-control" name="subscription_points" value="50">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Moeda dos Pontos</label>
                                                <input type="text" class="form-control" name="points_currency" value="Pontos">
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-custom">
                                        <i class="fas fa-save"></i> Salvar Configurações
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="dashboard-card">
                                <h5 class="mb-3">Informações do Sistema</h5>
                                <div class="info-item mb-3">
                                    <strong>Versão:</strong> 1.0.0
                                </div>
                                <div class="info-item mb-3">
                                    <strong>PHP:</strong> <?php echo PHP_VERSION; ?>
                                </div>
                                <div class="info-item mb-3">
                                    <strong>Servidor:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?>
                                </div>
                                <div class="info-item mb-3">
                                    <strong>Última Atualização:</strong> <?php echo date('d/m/Y H:i'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let currentSection = 'dashboard';
        let revenueChart = null;
        
        // API Base URL
        const API_BASE = 'http://localhost:8080/api';
        
        // Função para fazer chamadas à API
        async function apiCall(endpoint, options = {}) {
            try {
                const url = `${API_BASE}/${endpoint}`;
                console.log('API URL:', url);
                
                const response = await fetch(url, {
                    headers: {
                        'Content-Type': 'application/json',
                        ...options.headers
                    },
                    ...options
                });
                
                console.log('Response status:', response.status);
                
                const responseText = await response.text();
                console.log('Response text:', responseText);
                
                const data = JSON.parse(responseText);
                return data;
            } catch (error) {
                console.error('API Error:', error);
                Swal.fire('Erro', 'Erro de conexão com a API', 'error');
                return null;
            }
        }
        
        // Toggle sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        }
        
        // Navegação
        document.addEventListener('DOMContentLoaded', function() {
            // Event listeners para navegação
            document.querySelectorAll('.nav-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    const section = this.dataset.section;
                    if (section) {
                        e.preventDefault();
                        navigateToSection(section);
                    }
                    // Se não tem data-section, deixa o link funcionar normalmente (como logout)
                });
            });
            
            // Toggle sidebar
            document.getElementById('toggleSidebar').addEventListener('click', toggleSidebar);
            
            // Carregar dashboard inicial
            loadDashboard();
        });
        
        function navigateToSection(section) {
            // Remover active de todos os links e seções
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            document.querySelectorAll('.section').forEach(sec => {
                sec.classList.remove('active');
                sec.style.display = 'none';
            });
            
            // Ativar link e seção atual
            document.querySelector(`[data-section="${section}"]`).classList.add('active');
            const sectionElement = document.getElementById(section);
            sectionElement.classList.add('active');
            sectionElement.style.display = 'block';
            
            // Atualizar título
            const titles = {
                dashboard: 'Dashboard',
                users: 'Usuários',
                subscriptions: 'Assinaturas',
                payments: 'Pagamentos',
                plans: 'Planos',
                rewards: 'Recompensas',
                points: 'Sistema de Pontos',
                settings: 'Configurações'
            };
            document.getElementById('pageTitle').textContent = titles[section] || 'Dashboard';
            
            currentSection = section;
            
            // Carregar dados da seção
            switch(section) {
                case 'dashboard':
                    loadDashboard();
                    break;
                case 'users':
                    loadUsers();
                    break;
                case 'subscriptions':
                    loadSubscriptions();
                    break;
                case 'payments':
                    loadPayments();
                    break;
                case 'plans':
                    loadPlans();
                    break;
                case 'rewards':
                    loadRewards();
                    break;
                case 'points':
                    loadPoints();
                    break;
                case 'settings':
                    loadSettings();
                    break;
            }
        }
        
        // Carregar Dashboard
        async function loadDashboard() {
            try {
                const data = await apiCall('dashboard.php?action=admin');
                if (data && data.success) {
                    const stats = data.data.stats;
                    
                    // Atualizar estatísticas
                    document.getElementById('totalUsers').textContent = stats.total_users || '0';
                    document.getElementById('activeSubscriptions').textContent = stats.active_subscriptions || '0';
                    document.getElementById('monthlyRevenue').textContent = 'R$ ' + (stats.monthly_revenue || '0,00');
                    document.getElementById('pendingPayments').textContent = stats.pending_payments || '0';
                    
                    // Carregar gráfico
                    loadRevenueChart(data.data.charts?.monthly_revenue || []);
                    
                    // Carregar atividade recente
                    loadRecentActivity(data.data.recent_activity || []);
                }
            } catch (error) {
                console.error('Erro ao carregar dashboard:', error);
            }
        }
        
        // Carregar gráfico de receita
        function loadRevenueChart(data) {
            const ctx = document.getElementById('revenueChart');
            if (!ctx) return;
            
            if (revenueChart) {
                revenueChart.destroy();
            }
            
            revenueChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(item => item.month) || ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun'],
                    datasets: [{
                        label: 'Receita (R$)',
                        data: data.map(item => item.revenue) || [1000, 1500, 2000, 1800, 2500, 3000],
                        borderColor: '#9333ea',
                        backgroundColor: 'rgba(147, 51, 234, 0.1)',
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
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: 'rgba(255,255,255,0.7)',
                                callback: function(value) {
                                    return 'R$ ' + value.toLocaleString();
                                }
                            },
                            grid: {
                                color: 'rgba(255,255,255,0.1)'
                            }
                        },
                        x: {
                            ticks: {
                                color: 'rgba(255,255,255,0.7)'
                            },
                            grid: {
                                color: 'rgba(255,255,255,0.1)'
                            }
                        }
                    }
                }
            });
        }
        
        // Carregar atividade recente
        function loadRecentActivity(activities = []) {
            const container = document.getElementById('recentActivity');
            
            if (activities.length > 0) {
                container.innerHTML = activities.map(activity => `
                    <div class="d-flex align-items-center mb-3 p-2 rounded" style="background: rgba(255,255,255,0.05);">
                        <div class="flex-shrink-0">
                            <i class="fas fa-${activity.icon || 'info-circle'}" style="color: #9333ea;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="fw-bold">${activity.title}</div>
                            <small class="text-muted">${activity.time}</small>
                        </div>
                    </div>
                `).join('');
            } else {
                container.innerHTML = `
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-inbox fa-2x mb-2" style="color: rgba(255,255,255,0.3);"></i>
                        <p>Nenhuma atividade recente</p>
                    </div>
                `;
            }
        }
        
        // Carregar Usuários
        async function loadUsers() {
            try {
                const data = await apiCall('users.php?action=list');
                const tbody = document.getElementById('usersTableBody');
                
                if (data && data.success && data.data) {
                    tbody.innerHTML = data.data.map(user => `
                        <tr>
                            <td>${user.id}</td>
                            <td>${user.name}</td>
                            <td>${user.email}</td>
                            <td><span class="badge bg-${user.role === 'admin' ? 'danger' : 'primary'}">${user.role}</span></td>
                            <td><span class="badge bg-${user.status === 'active' ? 'success' : 'secondary'}">${user.status}</span></td>
                            <td>${new Date(user.created_at).toLocaleDateString('pt-BR')}</td>
                            <td>
                                <button class="btn btn-sm btn-warning me-1" onclick="editUser(${user.id})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteUser(${user.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4">Nenhum usuário encontrado</td></tr>';
                }
            } catch (error) {
                document.getElementById('usersTableBody').innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">Erro ao carregar usuários</td></tr>';
            }
        }
        
        // Carregar Assinaturas
        async function loadSubscriptions() {
            try {
                const data = await apiCall('subscriptions.php?action=list');
                const tbody = document.getElementById('subscriptionsTableBody');
                
                if (data && data.success && data.data) {
                    tbody.innerHTML = data.data.map(sub => `
                        <tr>
                            <td>${sub.id}</td>
                            <td>${sub.user_name}</td>
                            <td>${sub.plan_name}</td>
                            <td><span class="badge bg-${sub.status === 'active' ? 'success' : 'secondary'}">${sub.status}</span></td>
                            <td>${new Date(sub.start_date).toLocaleDateString('pt-BR')}</td>
                            <td>${new Date(sub.end_date).toLocaleDateString('pt-BR')}</td>
                            <td>
                                <button class="btn btn-sm btn-warning me-1" onclick="editSubscription(${sub.id})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteSubscription(${sub.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4">Nenhuma assinatura encontrada</td></tr>';
                }
            } catch (error) {
                document.getElementById('subscriptionsTableBody').innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">Erro ao carregar assinaturas</td></tr>';
            }
        }
        
        // Carregar Pagamentos
        async function loadPayments() {
            try {
                const data = await apiCall('payments.php?action=list');
                const tbody = document.getElementById('paymentsTableBody');
                
                if (data && data.success && data.data) {
                    tbody.innerHTML = data.data.map(payment => `
                        <tr>
                            <td>${payment.id}</td>
                            <td>${payment.user_name}</td>
                            <td>R$ ${parseFloat(payment.amount).toFixed(2)}</td>
                            <td>${payment.payment_method}</td>
                            <td><span class="badge bg-${payment.status === 'completed' ? 'success' : payment.status === 'pending' ? 'warning' : 'danger'}">${payment.status}</span></td>
                            <td>${new Date(payment.created_at).toLocaleDateString('pt-BR')}</td>
                            <td>
                                <button class="btn btn-sm btn-info me-1" onclick="viewPayment(${payment.id})">
                                    <i class="fas fa-eye"></i>
                                </button>
                                ${payment.status === 'pending' ? `<button class="btn btn-sm btn-success" onclick="approvePayment(${payment.id})"><i class="fas fa-check"></i></button>` : ''}
                            </td>
                        </tr>
                    `).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4">Nenhum pagamento encontrado</td></tr>';
                }
            } catch (error) {
                document.getElementById('paymentsTableBody').innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">Erro ao carregar pagamentos</td></tr>';
            }
        }
        
        // Carregar Planos
        async function loadPlans() {
            try {
                const data = await apiCall('plans.php?action=list');
                const tbody = document.getElementById('plansTableBody');
                
                if (data && data.success && data.data) {
                    tbody.innerHTML = data.data.map(plan => `
                        <tr>
                            <td>${plan.id}</td>
                            <td>${plan.name}</td>
                            <td>${plan.duration_months} mês(es)</td>
                            <td>R$ ${parseFloat(plan.price).toFixed(2)}</td>
                            <td>${plan.discount_percentage || 0}%</td>
                            <td><span class="badge bg-${plan.is_active ? 'success' : 'secondary'}">${plan.is_active ? 'Ativo' : 'Inativo'}</span></td>
                            <td>
                                <button class="btn btn-sm btn-warning me-1" onclick="editPlan(${plan.id})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deletePlan(${plan.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4">Nenhum plano encontrado</td></tr>';
                }
            } catch (error) {
                document.getElementById('plansTableBody').innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">Erro ao carregar planos</td></tr>';
            }
        }
        
        // Carregar Recompensas
        async function loadRewards() {
            try {
                const data = await apiCall('points.php?action=rewards');
                const tbody = document.getElementById('rewardsTableBody');
                
                if (data && data.success && data.data && Array.isArray(data.data)) {
                    if (data.data.length > 0) {
                        tbody.innerHTML = data.data.map(reward => `
                            <tr>
                                <td>${reward.id || 'N/A'}</td>
                                <td>${reward.name || 'Sem nome'}</td>
                                <td>${reward.points_required || 0}</td>
                                <td>${reward.stock || reward.max_redemptions || 'Ilimitado'}</td>
                                <td><span class="badge bg-${reward.is_active == 1 || reward.is_active === true ? 'success' : 'secondary'}">${reward.is_active == 1 || reward.is_active === true ? 'Ativo' : 'Inativo'}</span></td>
                                <td>
                                    <button class="btn btn-sm btn-warning me-1" onclick="editReward(${reward.id})">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteReward(${reward.id})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `).join('');
                    } else {
                        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4">Nenhuma recompensa cadastrada</td></tr>';
                    }
                } else {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-warning">Erro na resposta da API ou dados inválidos</td></tr>';
                }
            } catch (error) {
                document.getElementById('rewardsTableBody').innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger">Erro ao carregar recompensas: ' + error.message + '</td></tr>';
            }
        }
        
        // Carregar Sistema de Pontos
        async function loadPoints() {
            // Carregar usuários para o select
            try {
                const usersData = await apiCall('users.php?action=list');
                const userSelect = document.querySelector('#awardPointsForm select[name="user_id"]');
                
                if (usersData && usersData.success && usersData.data) {
                    userSelect.innerHTML = '<option value="">Selecione um usuário</option>' +
                        usersData.data.map(user => `<option value="${user.id}">${user.name} (${user.email})</option>`).join('');
                }
            } catch (error) {
                console.error('Erro ao carregar usuários:', error);
            }
            
            // Carregar histórico de pontos
            loadPointsHistory();
        }
        
        // Carregar histórico de pontos
        async function loadPointsHistory() {
            try {
                const data = await apiCall('points.php?action=history');
                const container = document.getElementById('pointsHistory');
                
                if (data && data.success && data.data && data.data.length > 0) {
                    container.innerHTML = data.data.map(entry => `
                        <div class="d-flex justify-content-between align-items-center mb-2 p-2 rounded" style="background: rgba(255,255,255,0.05);">
                            <div>
                                <strong>${entry.user_name}</strong><br>
                                <small class="text-muted">${entry.reason || 'Pontos concedidos'}</small>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-success">+${entry.points}</span><br>
                                <small class="text-muted">${new Date(entry.created_at).toLocaleDateString('pt-BR')}</small>
                            </div>
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = '<div class="text-center py-4 style="color: white;">Nenhum histórico de pontos</div>';
                }
            } catch (error) {
                document.getElementById('pointsHistory').innerHTML = '<div class="text-center text-danger py-4">Erro ao carregar histórico</div>';
            }
        }
        
        // Carregar Configurações
        function loadSettings() {
            // As configurações já estão carregadas no HTML
            console.log('Configurações carregadas');
        }
        
        // Event Listeners para formulários
        document.addEventListener('DOMContentLoaded', function() {
            // Formulário de conceder pontos
            document.getElementById('awardPointsForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                const data = Object.fromEntries(formData);
                
                try {
                    const response = await apiCall('points.php?action=award', {
                        method: 'POST',
                        body: JSON.stringify(data)
                    });
                    
                    if (response && response.success) {
                        Swal.fire('Sucesso', 'Pontos concedidos com sucesso!', 'success');
                        this.reset();
                        loadPointsHistory();
                    } else {
                        Swal.fire('Erro', response?.message || 'Erro ao conceder pontos', 'error');
                    }
                } catch (error) {
                    Swal.fire('Erro', 'Erro de conexão', 'error');
                }
            });
            
            // Formulário de configurações
            document.getElementById('settingsForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                const data = Object.fromEntries(formData);
                
                try {
                    const response = await apiCall('settings.php?action=update', {
                        method: 'POST',
                        body: JSON.stringify(data)
                    });
                    
                    if (response && response.success) {
                        Swal.fire('Sucesso', 'Configurações salvas com sucesso!', 'success');
                    } else {
                        Swal.fire('Erro', response?.message || 'Erro ao salvar configurações', 'error');
                    }
                } catch (error) {
                    Swal.fire('Erro', 'Erro de conexão', 'error');
                }
            });
        });
        
        // Funções de ação (placeholders)
        function showCreateUserModal() {
            Swal.fire({
                title: 'Novo Usuário',
                html: `
                    <div class="modern-form text-start" style="background: rgba(255,255,255,0.05); backdrop-filter: blur(10px); border-radius: 15px; padding: 2rem; border: 1px solid rgba(255,255,255,0.1);">
                        <div class="form-group mb-4">
                            <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-user" style="color: var(--purple-primary);"></i>
                                Nome Completo
                            </label>
                            <input type="text" id="userName" class="form-control" placeholder="Ex: João Silva" 
                                   style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                        </div>
                        <div class="form-group mb-4">
                            <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-envelope" style="color: var(--purple-primary);"></i>
                                Email
                            </label>
                            <input type="email" id="userEmail" class="form-control" placeholder="usuario@exemplo.com"
                                   style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                        </div>
                        <div class="form-group mb-4">
                            <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-phone" style="color: var(--purple-primary);"></i>
                                Telefone
                            </label>
                            <input type="tel" id="userPhone" class="form-control" placeholder="(11) 99999-9999"
                                   style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-lock" style="color: var(--purple-primary);"></i>
                                        Senha
                                    </label>
                                    <input type="password" id="userPassword" class="form-control" placeholder="Senha segura"
                                           style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-user-tag" style="color: var(--purple-primary);"></i>
                                        Tipo de Usuário
                                    </label>
                                    <select id="userType" class="form-select"
                                            style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                                        <option value="user" selected style="background: #1a1a2e; color: white;">Usuário</option>
                                        <option value="admin" style="background: #1a1a2e; color: white;">Administrador</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Criar Usuário',
                cancelButtonText: 'Cancelar',
                width: '500px',
                preConfirm: () => {
                    const name = document.getElementById('userName').value;
                    const email = document.getElementById('userEmail').value;
                    const phone = document.getElementById('userPhone').value;
                    const password = document.getElementById('userPassword').value;
                    const userType = document.getElementById('userType').value;
                    
                    if (!name || !email || !password) {
                        Swal.showValidationMessage('Por favor, preencha todos os campos obrigatórios');
                        return false;
                    }
                    
                    return {
                        name: name,
                        email: email,
                        phone: phone,
                        password: password,
                        user_type: userType
                    };
                }
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                        const response = await apiCall('users.php?action=create', {
                            method: 'POST',
                            body: JSON.stringify(result.value)
                        });
                        
                        if (response && response.success) {
                            Swal.fire('Sucesso!', 'Usuário criado com sucesso!', 'success');
                            loadUsers(); // Recarregar lista
                        } else {
                            Swal.fire('Erro', response?.message || 'Erro ao criar usuário', 'error');
                        }
                    } catch (error) {
                        Swal.fire('Erro', 'Erro de conexão com a API', 'error');
                    }
                }
            });
        }
        
        async function editUser(id) {
            try {
                const userData = await apiCall(`users.php?action=profile&id=${id}`);
                
                if (!userData || !userData.success) {
                    Swal.fire('Erro', 'Erro ao carregar dados do usuário', 'error');
                    return;
                }
                
                const user = userData.data.user;
                
                Swal.fire({
                    title: 'Editar Usuário',
                    html: `
                        <div class="modern-form text-start" style="background: rgba(255,255,255,0.05); backdrop-filter: blur(10px); border-radius: 15px; padding: 2rem; border: 1px solid rgba(255,255,255,0.1);">
                            <div class="form-group mb-4">
                                <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-user" style="color: var(--purple-primary);"></i>
                                    Nome Completo
                                </label>
                                <input type="text" id="editUserName" class="form-control" value="${user.name || ''}" 
                                       style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                            </div>
                            <div class="form-group mb-4">
                                <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-envelope" style="color: var(--purple-primary);"></i>
                                    Email
                                </label>
                                <input type="email" id="editUserEmail" class="form-control" value="${user.email || ''}"
                                       style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                            </div>
                            <div class="form-group mb-4">
                                <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-phone" style="color: var(--purple-primary);"></i>
                                    Telefone
                                </label>
                                <input type="tel" id="editUserPhone" class="form-control" value="${user.phone || ''}"
                                       style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-user-tag" style="color: var(--purple-primary);"></i>
                                            Tipo de Usuário
                                        </label>
                                        <select id="editUserType" class="form-select"
                                                style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                                            <option value="user" ${user.user_type === 'user' ? 'selected' : ''} style="background: #1a1a2e; color: white;">Usuário</option>
                                            <option value="admin" ${user.user_type === 'admin' ? 'selected' : ''} style="background: #1a1a2e; color: white;">Administrador</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-toggle-on" style="color: var(--purple-primary);"></i>
                                            Status
                                        </label>
                                        <select id="editUserStatus" class="form-select"
                                                style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                                            <option value="active" ${user.status === 'active' ? 'selected' : ''} style="background: #1a1a2e; color: white;">Ativo</option>
                                            <option value="inactive" ${user.status === 'inactive' ? 'selected' : ''} style="background: #1a1a2e; color: white;">Inativo</option>
                                            <option value="suspended" ${user.status === 'suspended' ? 'selected' : ''} style="background: #1a1a2e; color: white;">Suspenso</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Salvar Alterações',
                    cancelButtonText: 'Cancelar',
                    width: '500px',
                    preConfirm: () => {
                        const name = document.getElementById('editUserName').value;
                        const email = document.getElementById('editUserEmail').value;
                        const phone = document.getElementById('editUserPhone').value;
                        const userType = document.getElementById('editUserType').value;
                        const status = document.getElementById('editUserStatus').value;
                        
                        if (!name || !email) {
                            Swal.showValidationMessage('Por favor, preencha todos os campos obrigatórios');
                            return false;
                        }
                        
                        return {
                            id: id,
                            name: name,
                            email: email,
                            phone: phone,
                            user_type: userType,
                            status: status
                        };
                    }
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        try {
                            const response = await apiCall('users.php?action=update', {
                                method: 'POST',
                                body: JSON.stringify(result.value)
                            });
                            
                            if (response && response.success) {
                                Swal.fire('Sucesso!', 'Usuário atualizado com sucesso!', 'success');
                                loadUsers(); // Recarregar lista
                            } else {
                                Swal.fire('Erro', response?.message || 'Erro ao atualizar usuário', 'error');
                            }
                        } catch (error) {
                            Swal.fire('Erro', 'Erro de conexão com a API', 'error');
                        }
                    }
                });
                
            } catch (error) {
                Swal.fire('Erro', 'Erro ao carregar dados do usuário', 'error');
            }
        }
        
        function deleteUser(id) {
            Swal.fire({
                title: 'Confirmar Exclusão',
                text: 'Tem certeza que deseja excluir este usuário? Esta ação não pode ser desfeita.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#ef4444'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                        const response = await apiCall('users.php?action=delete', {
                            method: 'POST',
                            body: JSON.stringify({ id: id })
                        });
                        
                        if (response && response.success) {
                            Swal.fire('Excluído!', 'Usuário excluído com sucesso!', 'success');
                            loadUsers(); // Recarregar lista
                        } else {
                            Swal.fire('Erro', response?.message || 'Erro ao excluir usuário', 'error');
                        }
                    } catch (error) {
                        Swal.fire('Erro', 'Erro de conexão com a API', 'error');
                    }
                }
            });
        }
        
        async function showCreateSubscriptionModal() {
            try {
                // Carregar usuários e planos para os selects
                const [usersData, plansData] = await Promise.all([
                    apiCall('users.php?action=list'),
                    apiCall('plans.php?action=list')
                ]);
                
                const users = usersData?.data || [];
                const plans = plansData?.data || [];
                
                const userOptions = users.map(user => 
                    `<option value="${user.id}" style="background: #1a1a2e; color: white;">${user.name} (${user.email})</option>`
                ).join('');
                
                const planOptions = plans.map(plan => 
                    `<option value="${plan.id}" style="background: #1a1a2e; color: white;">${plan.name} - R$ ${plan.price}</option>`
                ).join('');
                
                Swal.fire({
                    title: 'Nova Assinatura',
                    html: `
                        <div class="modern-form text-start" style="background: rgba(255,255,255,0.05); backdrop-filter: blur(10px); border-radius: 15px; padding: 2rem; border: 1px solid rgba(255,255,255,0.1);">
                            <div class="form-group mb-4">
                                <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-user" style="color: var(--purple-primary);"></i>
                                    Usuário
                                </label>
                                <select id="subscriptionUser" class="form-select"
                                        style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                                    <option value="" style="background: #1a1a2e; color: white;">Selecione um usuário</option>
                                    ${userOptions}
                                </select>
                            </div>
                            <div class="form-group mb-4">
                                <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-box" style="color: var(--purple-primary);"></i>
                                    Plano
                                </label>
                                <select id="subscriptionPlan" class="form-select"
                                        style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                                    <option value="" style="background: #1a1a2e; color: white;">Selecione um plano</option>
                                    ${planOptions}
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-calendar-alt" style="color: var(--purple-primary);"></i>
                                            Data de Início
                                        </label>
                                        <input type="date" id="subscriptionStartDate" class="form-control" value="${new Date().toISOString().split('T')[0]}"
                                               style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-toggle-on" style="color: var(--purple-primary);"></i>
                                            Status
                                        </label>
                                        <select id="subscriptionStatus" class="form-select"
                                                style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                                            <option value="active" selected style="background: #1a1a2e; color: white;">Ativo</option>
                                            <option value="inactive" style="background: #1a1a2e; color: white;">Inativo</option>
                                            <option value="expired" style="background: #1a1a2e; color: white;">Expirado</option>
                                            <option value="cancelled" style="background: #1a1a2e; color: white;">Cancelado</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Criar Assinatura',
                    cancelButtonText: 'Cancelar',
                    width: '500px',
                    preConfirm: () => {
                        const userId = document.getElementById('subscriptionUser').value;
                        const planId = document.getElementById('subscriptionPlan').value;
                        const startDate = document.getElementById('subscriptionStartDate').value;
                        const status = document.getElementById('subscriptionStatus').value;
                        
                        if (!userId || !planId || !startDate) {
                            Swal.showValidationMessage('Por favor, preencha todos os campos obrigatórios');
                            return false;
                        }
                        
                        return {
                            user_id: userId,
                            plan_id: planId,
                            start_date: startDate,
                            status: status
                        };
                    }
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        try {
                            const response = await apiCall('subscriptions.php?action=create', {
                                method: 'POST',
                                body: JSON.stringify(result.value)
                            });
                            
                            if (response && response.success) {
                                Swal.fire('Sucesso!', 'Assinatura criada com sucesso!', 'success');
                                loadSubscriptions(); // Recarregar lista
                            } else {
                                Swal.fire('Erro', response?.message || 'Erro ao criar assinatura', 'error');
                            }
                        } catch (error) {
                            Swal.fire('Erro', 'Erro de conexão com a API', 'error');
                        }
                    }
                });
                
            } catch (error) {
                Swal.fire('Erro', 'Erro ao carregar dados necessários', 'error');
            }
        }
        
        async function editSubscription(id) {
            try {
                // Carregar dados da assinatura, usuários e planos
                const [subscriptionData, usersData, plansData] = await Promise.all([
                    apiCall(`subscriptions.php?action=get&id=${id}`),
                    apiCall('users.php?action=list'),
                    apiCall('plans.php?action=list')
                ]);
                
                if (!subscriptionData || !subscriptionData.success) {
                    Swal.fire('Erro', 'Erro ao carregar dados da assinatura', 'error');
                    return;
                }
                
                const subscription = subscriptionData.data;
                const users = usersData?.data || [];
                const plans = plansData?.data || [];
                
                const userOptions = users.map(user => 
                    `<option value="${user.id}" ${user.id == subscription.user_id ? 'selected' : ''} style="background: #1a1a2e; color: white;">${user.name} (${user.email})</option>`
                ).join('');
                
                const planOptions = plans.map(plan => 
                    `<option value="${plan.id}" ${plan.id == subscription.plan_id ? 'selected' : ''} style="background: #1a1a2e; color: white;">${plan.name} - R$ ${plan.price}</option>`
                ).join('');
                
                Swal.fire({
                    title: 'Editar Assinatura',
                    html: `
                        <div class="modern-form text-start" style="background: rgba(255,255,255,0.05); backdrop-filter: blur(10px); border-radius: 15px; padding: 2rem; border: 1px solid rgba(255,255,255,0.1);">
                            <div class="form-group mb-4">
                                <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-user" style="color: var(--purple-primary);"></i>
                                    Usuário
                                </label>
                                <select id="editSubscriptionUser" class="form-select"
                                        style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                                    ${userOptions}
                                </select>
                            </div>
                            <div class="form-group mb-4">
                                <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-box" style="color: var(--purple-primary);"></i>
                                    Plano
                                </label>
                                <select id="editSubscriptionPlan" class="form-select"
                                        style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                                    ${planOptions}
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-calendar-alt" style="color: var(--purple-primary);"></i>
                                            Data de Início
                                        </label>
                                        <input type="date" id="editSubscriptionStartDate" class="form-control" value="${subscription.start_date ? subscription.start_date.split(' ')[0] : ''}"
                                               style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-toggle-on" style="color: var(--purple-primary);"></i>
                                            Status
                                        </label>
                                        <select id="editSubscriptionStatus" class="form-select"
                                                style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                                            <option value="active" ${subscription.status === 'active' ? 'selected' : ''} style="background: #1a1a2e; color: white;">Ativo</option>
                                            <option value="inactive" ${subscription.status === 'inactive' ? 'selected' : ''} style="background: #1a1a2e; color: white;">Inativo</option>
                                            <option value="expired" ${subscription.status === 'expired' ? 'selected' : ''} style="background: #1a1a2e; color: white;">Expirado</option>
                                            <option value="cancelled" ${subscription.status === 'cancelled' ? 'selected' : ''} style="background: #1a1a2e; color: white;">Cancelado</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Salvar Alterações',
                    cancelButtonText: 'Cancelar',
                    width: '500px',
                    preConfirm: () => {
                        const userId = document.getElementById('editSubscriptionUser').value;
                        const planId = document.getElementById('editSubscriptionPlan').value;
                        const startDate = document.getElementById('editSubscriptionStartDate').value;
                        const status = document.getElementById('editSubscriptionStatus').value;
                        
                        if (!userId || !planId || !startDate) {
                            Swal.showValidationMessage('Por favor, preencha todos os campos obrigatórios');
                            return false;
                        }
                        
                        return {
                            id: id,
                            user_id: userId,
                            plan_id: planId,
                            start_date: startDate,
                            status: status
                        };
                    }
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        try {
                            const response = await apiCall('subscriptions.php?action=update', {
                                method: 'POST',
                                body: JSON.stringify(result.value)
                            });
                            
                            if (response && response.success) {
                                Swal.fire('Sucesso!', 'Assinatura atualizada com sucesso!', 'success');
                                loadSubscriptions(); // Recarregar lista
                            } else {
                                Swal.fire('Erro', response?.message || 'Erro ao atualizar assinatura', 'error');
                            }
                        } catch (error) {
                            Swal.fire('Erro', 'Erro de conexão com a API', 'error');
                        }
                    }
                });
                
            } catch (error) {
                Swal.fire('Erro', 'Erro ao carregar dados da assinatura', 'error');
            }
        }
        
        function deleteSubscription(id) {
            Swal.fire({
                title: 'Confirmar Exclusão',
                text: 'Tem certeza que deseja excluir esta assinatura? Esta ação não pode ser desfeita.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#ef4444'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                        const response = await apiCall('subscriptions.php?action=delete', {
                            method: 'POST',
                            body: JSON.stringify({ id: id })
                        });
                        
                        if (response && response.success) {
                            Swal.fire('Excluído!', 'Assinatura excluída com sucesso!', 'success');
                            loadSubscriptions(); // Recarregar lista
                        } else {
                            Swal.fire('Erro', response?.message || 'Erro ao excluir assinatura', 'error');
                        }
                    } catch (error) {
                        Swal.fire('Erro', 'Erro de conexão com a API', 'error');
                    }
                }
            });
        }
        
        async function viewPayment(id) {
            try {
                const response = await apiCall(`payments.php?action=get&id=${id}`);
                
                if (!response || !response.success) {
                    Swal.fire('Erro', 'Erro ao carregar dados do pagamento', 'error');
                    return;
                }
                
                const payment = response.data;
                
                Swal.fire({
                    title: 'Detalhes do Pagamento',
                    html: `
                        <div class="modern-form text-start" style="background: rgba(255,255,255,0.05); backdrop-filter: blur(10px); border-radius: 15px; padding: 2rem; border: 1px solid rgba(255,255,255,0.1);">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-hashtag" style="color: var(--purple-primary);"></i>
                                        ID do Pagamento
                                    </label>
                                    <p style="color: #ccc; margin: 0; padding: 0.5rem; background: rgba(255,255,255,0.1); border-radius: 8px;">${payment.id}</p>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-user" style="color: var(--purple-primary);"></i>
                                        Usuário
                                    </label>
                                    <p style="color: #ccc; margin: 0; padding: 0.5rem; background: rgba(255,255,255,0.1); border-radius: 8px;">${payment.user_name || 'N/A'}</p>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-dollar-sign" style="color: var(--purple-primary);"></i>
                                        Valor
                                    </label>
                                    <p style="color: #ccc; margin: 0; padding: 0.5rem; background: rgba(255,255,255,0.1); border-radius: 8px;">R$ ${parseFloat(payment.amount || 0).toFixed(2)}</p>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-credit-card" style="color: var(--purple-primary);"></i>
                                        Método
                                    </label>
                                    <p style="color: #ccc; margin: 0; padding: 0.5rem; background: rgba(255,255,255,0.1); border-radius: 8px;">${payment.payment_method || 'N/A'}</p>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-info-circle" style="color: var(--purple-primary);"></i>
                                        Status
                                    </label>
                                    <p style="color: #ccc; margin: 0; padding: 0.5rem; background: rgba(255,255,255,0.1); border-radius: 8px;">
                                        <span class="badge ${payment.status === 'completed' ? 'bg-success' : payment.status === 'pending' ? 'bg-warning' : 'bg-danger'}">
                                            ${payment.status === 'completed' ? 'Concluído' : payment.status === 'pending' ? 'Pendente' : 'Cancelado'}
                                        </span>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-calendar" style="color: var(--purple-primary);"></i>
                                        Data
                                    </label>
                                    <p style="color: #ccc; margin: 0; padding: 0.5rem; background: rgba(255,255,255,0.1); border-radius: 8px;">${new Date(payment.created_at).toLocaleString('pt-BR')}</p>
                                </div>
                            </div>
                            ${payment.transaction_id ? `
                                <div class="mb-3">
                                    <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-receipt" style="color: var(--purple-primary);"></i>
                                        ID da Transação
                                    </label>
                                    <p style="color: #ccc; margin: 0; padding: 0.5rem; background: rgba(255,255,255,0.1); border-radius: 8px; font-family: monospace;">${payment.transaction_id}</p>
                                </div>
                            ` : ''}
                            ${payment.notes ? `
                                <div class="mb-3">
                                    <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-sticky-note" style="color: var(--purple-primary);"></i>
                                        Observações
                                    </label>
                                    <p style="color: #ccc; margin: 0; padding: 0.5rem; background: rgba(255,255,255,0.1); border-radius: 8px;">${payment.notes}</p>
                                </div>
                            ` : ''}
                        </div>
                    `,
                    confirmButtonText: 'Fechar',
                    width: '600px'
                });
            } catch (error) {
                Swal.fire('Erro', 'Erro de conexão com a API', 'error');
            }
        }
        
        async function approvePayment(id) {
            try {
                const result = await Swal.fire({
                    title: 'Aprovar Pagamento',
                    text: 'Tem certeza que deseja aprovar este pagamento?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sim, aprovar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#28a745'
                });
                
                if (result.isConfirmed) {
                    const response = await apiCall('payments.php?action=approve', {
                        method: 'POST',
                        body: JSON.stringify({ id: id })
                    });
                    
                    if (response && response.success) {
                        Swal.fire('Sucesso!', 'Pagamento aprovado com sucesso!', 'success');
                        loadPayments(); // Recarregar lista
                    } else {
                        Swal.fire('Erro', response?.message || 'Erro ao aprovar pagamento', 'error');
                    }
                }
            } catch (error) {
                Swal.fire('Erro', 'Erro de conexão com a API', 'error');
            }
        }
        
        // Funções de Planos - FUNCIONAIS
        function showCreatePlanModal() {
            Swal.fire({
                title: 'Novo Plano',
                html: `
                    <div class="modern-form text-start" style="background: rgba(255,255,255,0.05); backdrop-filter: blur(10px); border-radius: 15px; padding: 2rem; border: 1px solid rgba(255,255,255,0.1);">
                        <div class="form-group mb-4">
                            <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-tag" style="color: var(--purple-primary);"></i>
                                Nome do Plano
                            </label>
                            <input type="text" id="planName" class="form-control" placeholder="Ex: Plano Premium" 
                                   style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                        </div>
                        <div class="form-group mb-4">
                            <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-align-left" style="color: var(--purple-primary);"></i>
                                Descrição
                            </label>
                            <textarea id="planDescription" class="form-control" rows="3" placeholder="Descrição detalhada do plano"
                                      style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease; resize: vertical;"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-calendar-alt" style="color: var(--purple-primary);"></i>
                                        Duração (meses)
                                    </label>
                                    <input type="number" id="planDuration" class="form-control" min="1" value="1"
                                           style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-dollar-sign" style="color: var(--purple-primary);"></i>
                                        Preço (R$)
                                    </label>
                                    <input type="number" id="planPrice" class="form-control" step="0.01" min="0" placeholder="0.00"
                                           style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-mobile-alt" style="color: var(--purple-primary);"></i>
                                        Máximo de Dispositivos
                                    </label>
                                    <input type="number" id="planMaxDevices" class="form-control" min="1" value="1"
                                           style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-video" style="color: var(--purple-primary);"></i>
                                        Qualidade
                                    </label>
                                    <select id="planQuality" class="form-select"
                                            style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                                        <option value="SD" style="background: #1a1a2e; color: white;">SD</option>
                                        <option value="HD" selected style="background: #1a1a2e; color: white;">HD</option>
                                        <option value="FHD" style="background: #1a1a2e; color: white;">Full HD</option>
                                        <option value="4K" style="background: #1a1a2e; color: white;">4K</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-star" style="color: var(--purple-primary);"></i>
                                        Plano Popular
                                    </label>
                                    <select id="planPopular" class="form-select"
                                            style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                                        <option value="0" selected style="background: #1a1a2e; color: white;">Não</option>
                                        <option value="1" style="background: #1a1a2e; color: white;">Sim</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-toggle-on" style="color: var(--purple-primary);"></i>
                                        Status
                                    </label>
                                    <select id="planStatus" class="form-select"
                                            style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                                        <option value="active" selected style="background: #1a1a2e; color: white;">Ativo</option>
                                        <option value="inactive" style="background: #1a1a2e; color: white;">Inativo</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-3">
                            <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-list" style="color: var(--purple-primary);"></i>
                                Recursos (um por linha)
                            </label>
                            <textarea id="planFeatures" class="form-control" rows="4" placeholder="Ex:\nStreaming em HD\nSuporte 24/7\nSem anúncios\nAcesso ilimitado"
                                      style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease; resize: vertical;"></textarea>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Criar Plano',
                cancelButtonText: 'Cancelar',
                width: '600px',
                preConfirm: () => {
                    const name = document.getElementById('planName').value;
                    const description = document.getElementById('planDescription').value;
                    const duration = document.getElementById('planDuration').value;
                    const price = document.getElementById('planPrice').value;
                    const maxDevices = document.getElementById('planMaxDevices').value;
                    const quality = document.getElementById('planQuality').value;
                    const isPopular = document.getElementById('planPopular').value;
                    const status = document.getElementById('planStatus').value;
                    const featuresText = document.getElementById('planFeatures').value;
                    
                    if (!name || !price || !duration) {
                        Swal.showValidationMessage('Por favor, preencha todos os campos obrigatórios');
                        return false;
                    }
                    
                    // Processar features
                    let features = null;
                    if (featuresText.trim()) {
                        features = featuresText.split('\n').filter(f => f.trim()).map(f => f.trim());
                    }
                    
                    return {
                        name: name,
                        description: description,
                        duration_months: parseInt(duration),
                        price: parseFloat(price),
                        max_devices: parseInt(maxDevices),
                        quality: quality,
                        is_popular: parseInt(isPopular),
                        status: status,
                        features: features
                    };
                }
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                        const response = await apiCall('plans.php?action=create', {
                            method: 'POST',
                            body: JSON.stringify(result.value)
                        });
                        
                        if (response && response.success) {
                            Swal.fire('Sucesso!', 'Plano criado com sucesso!', 'success');
                            loadPlans(); // Recarregar lista
                        } else {
                            Swal.fire('Erro', response?.message || 'Erro ao criar plano', 'error');
                        }
                    } catch (error) {
                        Swal.fire('Erro', 'Erro de conexão com a API', 'error');
                    }
                }
            });
        }
        
        async function editPlan(id) {
            try {
                // Buscar dados do plano
                const planData = await apiCall(`plans.php?action=get&id=${id}`);
                
                if (!planData || !planData.success) {
                    Swal.fire('Erro', 'Erro ao carregar dados do plano', 'error');
                    return;
                }
                
                const plan = planData.data;
                
                Swal.fire({
                    title: 'Editar Plano',
                    html: `
                        <div class="modern-form text-start" style="background: rgba(255,255,255,0.05); backdrop-filter: blur(10px); border-radius: 15px; padding: 2rem; border: 1px solid rgba(255,255,255,0.1);">
                            <div class="form-group mb-4">
                                <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-tag" style="color: var(--purple-primary);"></i>
                                    Nome do Plano
                                </label>
                                <input type="text" id="editPlanName" class="form-control" value="${plan.name || ''}"
                                       style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                            </div>
                            <div class="form-group mb-4">
                                <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-align-left" style="color: var(--purple-primary);"></i>
                                    Descrição
                                </label>
                                <textarea id="editPlanDescription" class="form-control" rows="3"
                                          style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease; resize: vertical;">${plan.description || ''}</textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-calendar-alt" style="color: var(--purple-primary);"></i>
                                            Duração (meses)
                                        </label>
                                        <input type="number" id="editPlanDuration" class="form-control" min="1" value="${plan.duration_months || 1}"
                                               style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-dollar-sign" style="color: var(--purple-primary);"></i>
                                            Preço (R$)
                                        </label>
                                        <input type="number" id="editPlanPrice" class="form-control" step="0.01" min="0" value="${plan.price || 0}"
                                               style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-mobile-alt" style="color: var(--purple-primary);"></i>
                                            Máximo de Dispositivos
                                        </label>
                                        <input type="number" id="editPlanMaxDevices" class="form-control" min="1" value="${plan.max_devices || 1}"
                                               style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-video" style="color: var(--purple-primary);"></i>
                                            Qualidade
                                        </label>
                                        <select id="editPlanQuality" class="form-select"
                                                style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                                            <option value="SD" ${plan.quality === 'SD' ? 'selected' : ''} style="background: #1a1a2e; color: white;">SD</option>
                                            <option value="HD" ${plan.quality === 'HD' ? 'selected' : ''} style="background: #1a1a2e; color: white;">HD</option>
                                            <option value="FHD" ${plan.quality === 'FHD' ? 'selected' : ''} style="background: #1a1a2e; color: white;">Full HD</option>
                                            <option value="4K" ${plan.quality === '4K' ? 'selected' : ''} style="background: #1a1a2e; color: white;">4K</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-star" style="color: var(--purple-primary);"></i>
                                            Plano Popular
                                        </label>
                                        <select id="editPlanPopular" class="form-select"
                                                style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                                            <option value="0" ${!plan.is_popular ? 'selected' : ''} style="background: #1a1a2e; color: white;">Não</option>
                                            <option value="1" ${plan.is_popular ? 'selected' : ''} style="background: #1a1a2e; color: white;">Sim</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-toggle-on" style="color: var(--purple-primary);"></i>
                                            Status
                                        </label>
                                        <select id="editPlanStatus" class="form-select"
                                                style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                                            <option value="active" ${plan.status === 'active' ? 'selected' : ''} style="background: #1a1a2e; color: white;">Ativo</option>
                                            <option value="inactive" ${plan.status === 'inactive' ? 'selected' : ''} style="background: #1a1a2e; color: white;">Inativo</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group mb-3">
                                <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-list" style="color: var(--purple-primary);"></i>
                                    Recursos (um por linha)
                                </label>
                                <textarea id="editPlanFeatures" class="form-control" rows="4" placeholder="Ex:\nStreaming em HD\nSuporte 24/7\nSem anúncios\nAcesso ilimitado"
                                          style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease; resize: vertical;">${Array.isArray(plan.features) ? plan.features.join('\n') : (plan.features || '')}</textarea>
                            </div>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Salvar Alterações',
                    cancelButtonText: 'Cancelar',
                    width: '600px',
                    preConfirm: () => {
                        const name = document.getElementById('editPlanName').value;
                        const description = document.getElementById('editPlanDescription').value;
                        const duration = document.getElementById('editPlanDuration').value;
                        const price = document.getElementById('editPlanPrice').value;
                        const maxDevices = document.getElementById('editPlanMaxDevices').value;
                        const quality = document.getElementById('editPlanQuality').value;
                        const isPopular = document.getElementById('editPlanPopular').value;
                        const status = document.getElementById('editPlanStatus').value;
                        const featuresText = document.getElementById('editPlanFeatures').value;
                        
                        if (!name || !price || !duration) {
                            Swal.showValidationMessage('Por favor, preencha todos os campos obrigatórios');
                            return false;
                        }
                        
                        // Processar features
                        let features = null;
                        if (featuresText.trim()) {
                            features = featuresText.split('\n').filter(f => f.trim()).map(f => f.trim());
                        }
                        
                        return {
                            id: id,
                            name: name,
                            description: description,
                            duration_months: parseInt(duration),
                            price: parseFloat(price),
                            max_devices: parseInt(maxDevices),
                            quality: quality,
                            is_popular: parseInt(isPopular),
                            status: status,
                            features: features
                        };
                    }
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        try {
                            const response = await apiCall('plans.php?action=update', {
                                method: 'POST',
                                body: JSON.stringify(result.value)
                            });
                            
                            if (response && response.success) {
                                Swal.fire('Sucesso!', 'Plano atualizado com sucesso!', 'success');
                                loadPlans(); // Recarregar lista
                            } else {
                                Swal.fire('Erro', response?.message || 'Erro ao atualizar plano', 'error');
                            }
                        } catch (error) {
                            Swal.fire('Erro', 'Erro de conexão com a API', 'error');
                        }
                    }
                });
                
            } catch (error) {
                Swal.fire('Erro', 'Erro ao carregar dados do plano', 'error');
            }
        }
        
        function deletePlan(id) {
            Swal.fire({
                title: 'Confirmar Exclusão',
                text: 'Tem certeza que deseja excluir este plano? Esta ação não pode ser desfeita.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#ef4444'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                        const response = await apiCall('plans.php?action=delete', {
                            method: 'POST',
                            body: JSON.stringify({ id: id })
                        });
                        
                        if (response && response.success) {
                            Swal.fire('Excluído!', 'Plano excluído com sucesso!', 'success');
                            loadPlans(); // Recarregar lista
                        } else {
                            Swal.fire('Erro', response?.message || 'Erro ao excluir plano', 'error');
                        }
                    } catch (error) {
                        Swal.fire('Erro', 'Erro de conexão com a API', 'error');
                    }
                }
            });
        }
        
        function showCreateRewardModal() {
            Swal.fire({
                title: 'Nova Recompensa',
                html: `
                    <div class="modern-form text-start" style="background: rgba(255,255,255,0.05); backdrop-filter: blur(10px); border-radius: 15px; padding: 2rem; border: 1px solid rgba(255,255,255,0.1);">
                        <div class="form-group mb-4">
                            <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-gift" style="color: var(--purple-primary);"></i>
                                Nome da Recompensa
                            </label>
                            <input type="text" id="rewardName" class="form-control" placeholder="Ex: Desconto 10%" 
                                   style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                        </div>
                        <div class="form-group mb-4">
                            <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-align-left" style="color: var(--purple-primary);"></i>
                                Descrição
                            </label>
                            <textarea id="rewardDescription" class="form-control" rows="3" placeholder="Descrição detalhada da recompensa"
                                      style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease; resize: vertical;"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-coins" style="color: var(--purple-primary);"></i>
                                        Pontos Necessários
                                    </label>
                                    <input type="number" id="rewardPoints" class="form-control" min="1" placeholder="100"
                                           style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-tag" style="color: var(--purple-primary);"></i>
                                        Tipo de Recompensa
                                    </label>
                                    <select id="rewardType" class="form-select"
                                            style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                                        <option value="produto" selected style="background: #1a1a2e; color: white;">Produto</option>
                                        <option value="desconto" style="background: #1a1a2e; color: white;">Desconto</option>
                                        <option value="mensalidade_gratis" style="background: #1a1a2e; color: white;">Mensalidade Grátis</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-boxes" style="color: var(--purple-primary);"></i>
                                        Estoque
                                    </label>
                                    <input type="number" id="rewardStock" class="form-control" min="0" placeholder="Ilimitado (deixe vazio)"
                                           style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-toggle-on" style="color: var(--purple-primary);"></i>
                                        Status
                                    </label>
                                    <select id="rewardStatus" class="form-select"
                                            style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                                        <option value="active" selected style="background: #1a1a2e; color: white;">Ativo</option>
                                        <option value="inactive" style="background: #1a1a2e; color: white;">Inativo</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Criar Recompensa',
                cancelButtonText: 'Cancelar',
                width: '500px',
                preConfirm: () => {
                    const name = document.getElementById('rewardName').value;
                    const description = document.getElementById('rewardDescription').value;
                    const points = document.getElementById('rewardPoints').value;
                    const rewardType = document.getElementById('rewardType').value;
                    const stock = document.getElementById('rewardStock').value;
                    const status = document.getElementById('rewardStatus').value;
                    
                    if (!name || !points) {
                        Swal.showValidationMessage('Por favor, preencha todos os campos obrigatórios');
                        return false;
                    }
                    
                    return {
                        name: name,
                        description: description,
                        points_required: parseInt(points),
                        reward_type: rewardType,
                        stock: stock ? parseInt(stock) : null,
                        status: status
                    };
                }
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                        const response = await apiCall('points.php?action=create_reward', {
                            method: 'POST',
                            body: JSON.stringify(result.value)
                        });
                        
                        if (response && response.success) {
                            Swal.fire('Sucesso!', 'Recompensa criada com sucesso!', 'success');
                            loadRewards(); // Recarregar lista
                        } else {
                            Swal.fire('Erro', response?.message || 'Erro ao criar recompensa', 'error');
                        }
                    } catch (error) {
                        Swal.fire('Erro', 'Erro de conexão com a API', 'error');
                    }
                }
            });
        }
        
        async function editReward(id) {
            try {
                const rewardData = await apiCall(`points.php?action=get_reward&id=${id}`);
                
                if (!rewardData || !rewardData.success) {
                    Swal.fire('Erro', 'Erro ao carregar dados da recompensa', 'error');
                    return;
                }
                
                const reward = rewardData.data;
                
                Swal.fire({
                    title: 'Editar Recompensa',
                    html: `
                        <div class="modern-form text-start" style="background: rgba(255,255,255,0.05); backdrop-filter: blur(10px); border-radius: 15px; padding: 2rem; border: 1px solid rgba(255,255,255,0.1);">
                            <div class="form-group mb-4">
                                <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-gift" style="color: var(--purple-primary);"></i>
                                    Nome da Recompensa
                                </label>
                                <input type="text" id="editRewardName" class="form-control" value="${reward.name || ''}" 
                                       style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                            </div>
                            <div class="form-group mb-4">
                                <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-align-left" style="color: var(--purple-primary);"></i>
                                    Descrição
                                </label>
                                <textarea id="editRewardDescription" class="form-control" rows="3"
                                          style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease; resize: vertical;">${reward.description || ''}</textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-coins" style="color: var(--purple-primary);"></i>
                                            Pontos Necessários
                                        </label>
                                        <input type="number" id="editRewardPoints" class="form-control" min="1" value="${reward.points_required || ''}"
                                               style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-tag" style="color: var(--purple-primary);"></i>
                                            Tipo de Recompensa
                                        </label>
                                        <select id="editRewardType" class="form-select"
                                                style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                                            <option value="produto" ${(reward.reward_type || reward.type) === 'produto' ? 'selected' : ''} style="background: #1a1a2e; color: white;">Produto</option>
                                            <option value="desconto" ${(reward.reward_type || reward.type) === 'desconto' ? 'selected' : ''} style="background: #1a1a2e; color: white;">Desconto</option>
                                            <option value="mensalidade_gratis" ${(reward.reward_type || reward.type) === 'mensalidade_gratis' ? 'selected' : ''} style="background: #1a1a2e; color: white;">Mensalidade Grátis</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-boxes" style="color: var(--purple-primary);"></i>
                                            Estoque
                                        </label>
                                        <input type="number" id="editRewardStock" class="form-control" min="0" value="${reward.stock || ''}"
                                               style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label class="form-label" style="color: white; font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-toggle-on" style="color: var(--purple-primary);"></i>
                                            Status
                                        </label>
                                        <select id="editRewardStatus" class="form-select"
                                                style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 10px; padding: 0.75rem 1rem; transition: all 0.3s ease;">
                                            <option value="active" ${reward.status === 'active' ? 'selected' : ''} style="background: #1a1a2e; color: white;">Ativo</option>
                                            <option value="inactive" ${reward.status === 'inactive' ? 'selected' : ''} style="background: #1a1a2e; color: white;">Inativo</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Salvar Alterações',
                    cancelButtonText: 'Cancelar',
                    width: '500px',
                    preConfirm: () => {
                        const name = document.getElementById('editRewardName').value;
                        const description = document.getElementById('editRewardDescription').value;
                        const points = document.getElementById('editRewardPoints').value;
                        const rewardType = document.getElementById('editRewardType').value;
                        const stock = document.getElementById('editRewardStock').value;
                        const status = document.getElementById('editRewardStatus').value;
                        
                        if (!name || !points) {
                            Swal.showValidationMessage('Por favor, preencha todos os campos obrigatórios');
                            return false;
                        }
                        
                        return {
                            id: id,
                            name: name,
                            description: description,
                            points_required: parseInt(points),
                            reward_type: rewardType,
                            stock: stock ? parseInt(stock) : null,
                            status: status
                        };
                    }
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        try {
                            const response = await apiCall('points.php?action=update_reward', {
                                method: 'POST',
                                body: JSON.stringify(result.value)
                            });
                            
                            if (response && response.success) {
                                Swal.fire('Sucesso!', 'Recompensa atualizada com sucesso!', 'success');
                                loadRewards(); // Recarregar lista
                            } else {
                                Swal.fire('Erro', response?.message || 'Erro ao atualizar recompensa', 'error');
                            }
                        } catch (error) {
                            Swal.fire('Erro', 'Erro de conexão com a API', 'error');
                        }
                    }
                });
                
            } catch (error) {
                Swal.fire('Erro', 'Erro ao carregar dados da recompensa', 'error');
            }
        }
        
        function deleteReward(id) {
            Swal.fire({
                title: 'Confirmar Exclusão',
                text: 'Tem certeza que deseja excluir esta recompensa? Esta ação não pode ser desfeita.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#ef4444'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                        const response = await apiCall('points.php?action=delete_reward', {
                            method: 'POST',
                            body: JSON.stringify({ id: id })
                        });
                        
                        if (response && response.success) {
                            Swal.fire('Excluído!', 'Recompensa excluída com sucesso!', 'success');
                            loadRewards(); // Recarregar lista
                        } else {
                            Swal.fire('Erro', response?.message || 'Erro ao excluir recompensa', 'error');
                        }
                    } catch (error) {
                        Swal.fire('Erro', 'Erro de conexão com a API', 'error');
                    }
                }
            });
        }
    </script>
</body>
</html>
