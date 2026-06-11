<?php
/**
 * Endpoint de Dashboard
 * KMKZ IPTV API
 * 
 * Endpoints disponíveis:
 * GET /dashboard.php?action=admin - Dashboard administrativo
 * GET /dashboard.php?action=user - Dashboard do usuário
 * GET /dashboard.php?action=stats - Estatísticas gerais
 * GET /dashboard.php?action=recent_activity - Atividade recente
 * GET /dashboard.php?action=notifications - Notificações
 * POST /dashboard.php?action=mark_notification_read - Marcar notificação como lida
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/middleware.php';

// Aplicar middleware de segurança
ApiMiddleware::apply();

// Obter ação
$action = $_GET['action'] ?? '';
$input = ApiUtils::getInput();

// Conectar ao banco
$db = getDB();
if (!$db) {
    echo ApiUtils::error('Erro de conexão com o banco de dados');
    exit();
}

switch ($action) {
    case 'admin':
        handleAdminDashboard($db);
        break;
        
    case 'user':
        handleUserDashboard($db);
        break;
        
    case 'stats':
        handleGeneralStats($db);
        break;
        
    case 'recent_activity':
        handleRecentActivity($db, $input);
        break;
        
    case 'notifications':
        handleGetNotifications($db, $input);
        break;
        
    case 'mark_notification_read':
        handleMarkNotificationRead($db, $input);
        break;
        
    default:
        echo ApiUtils::error('Ação não encontrada', 404);
        break;
}

/**
 * Dashboard administrativo
 */
function handleAdminDashboard($db) {
    ApiUtils::validateMethod(['GET']);
    ApiUtils::requireAdmin();
    
    try {
        // Estatísticas gerais
        $stats = getGeneralStats($db);

        $monthlyRevenueValue = 0.0;
        if (dbTableExists($db, 'payments') && dbColumnExists($db, 'payments', 'amount') && dbColumnExists($db, 'payments', 'created_at') && dbColumnExists($db, 'payments', 'status')) {
            $monthlyRevenueValue = (float)dbFetchValue(
                $db,
                "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed' AND DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')",
                [],
                0
            );
        }

        $pendingPaymentsCount = 0;
        if (dbTableExists($db, 'payments') && dbColumnExists($db, 'payments', 'status')) {
            $pendingPaymentsCount = (int)dbFetchValue($db, "SELECT COUNT(*) FROM payments WHERE status = 'pending'", [], 0);
        }

        $statsSummary = [
            'total_users' => (int)($stats['users']['total'] ?? 0),
            'active_subscriptions' => (int)($stats['users']['active_subscriptions'] ?? 0),
            'monthly_revenue' => number_format($monthlyRevenueValue, 2, ',', '.'),
            'pending_payments' => $pendingPaymentsCount,
            'details' => $stats
        ];
        
        // Receita mensal
        $monthlyRevenue = [];
        if (dbTableExists($db, 'payments') && dbColumnExists($db, 'payments', 'created_at') && dbColumnExists($db, 'payments', 'amount') && dbColumnExists($db, 'payments', 'status')) {
            $monthlyRevenue = dbFetchAll($db, "
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    SUM(amount) as revenue,
                    COUNT(*) as payments
                FROM payments 
                WHERE status = 'completed' 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month
            ");
        }
        
        // Crescimento de usuários
        $userGrowth = [];
        if (dbTableExists($db, 'users') && dbColumnExists($db, 'users', 'created_at')) {
            $userGrowth = dbFetchAll($db, "
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as new_users
                FROM users 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month
            ");
        }
        
        // Assinaturas por plano
        $subscriptionsByPlan = [];
        if (dbTableExists($db, 'subscriptions') && dbTableExists($db, 'plans') && dbColumnExists($db, 'subscriptions', 'plan_id') && dbColumnExists($db, 'plans', 'id')) {
            $where = dbColumnExists($db, 'subscriptions', 'status') ? "WHERE s.status = 'active'" : '';
            $subscriptionsByPlan = dbFetchAll($db, "
                SELECT 
                    p.name as plan_name,
                    COUNT(s.id) as subscriptions,
                    SUM(p.price) as revenue
                FROM subscriptions s
                JOIN plans p ON s.plan_id = p.id
                {$where}
                GROUP BY p.id
                ORDER BY subscriptions DESC
            ");
        }
        
        // Métodos de pagamento mais usados
        $paymentMethods = [];
        if (dbTableExists($db, 'payments') && dbColumnExists($db, 'payments', 'payment_method') && dbColumnExists($db, 'payments', 'amount')) {
            $where = dbColumnExists($db, 'payments', 'status') ? "WHERE status = 'completed'" : '';
            $paymentMethods = dbFetchAll($db, "
                SELECT 
                    payment_method,
                    COUNT(*) as count,
                    SUM(amount) as revenue
                FROM payments 
                {$where}
                GROUP BY payment_method
                ORDER BY count DESC
            ");
        }
        
        // Usuários mais ativos (por pontos)
        $topUsers = [];
        if (dbTableExists($db, 'users')) {
            $statusWhere = dbColumnExists($db, 'users', 'status') ? "WHERE u.status = 'active'" : '';
            $orderBy = dbColumnExists($db, 'users', 'points') ? 'u.points DESC' : (dbColumnExists($db, 'users', 'created_at') ? 'u.created_at DESC' : 'u.id DESC');
            $selectPoints = dbColumnExists($db, 'users', 'points') ? ', u.points' : '';
            $selectCreatedAt = dbColumnExists($db, 'users', 'created_at') ? ', u.created_at' : '';
            $topUsers = dbFetchAll($db, "
                SELECT 
                    u.id,
                    u.name,
                    u.email
                    {$selectPoints}
                    {$selectCreatedAt}
                FROM users u
                {$statusWhere}
                ORDER BY {$orderBy}
                LIMIT 10
            ");
        }
        
        // Assinaturas expirando nos próximos 7 dias
        $expiringSoon = [];
        if (
            dbTableExists($db, 'subscriptions') &&
            dbTableExists($db, 'users') &&
            dbTableExists($db, 'plans') &&
            dbColumnExists($db, 'subscriptions', 'end_date') &&
            dbColumnExists($db, 'subscriptions', 'user_id') &&
            dbColumnExists($db, 'subscriptions', 'plan_id')
        ) {
            $statusWhere = dbColumnExists($db, 'subscriptions', 'status') ? "s.status = 'active' AND " : '';
            $expiringSoon = dbFetchAll($db, "
                SELECT 
                    s.id,
                    s.end_date,
                    u.name as user_name,
                    u.email as user_email,
                    p.name as plan_name
                FROM subscriptions s
                JOIN users u ON s.user_id = u.id
                JOIN plans p ON s.plan_id = p.id
                WHERE {$statusWhere}
                s.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                ORDER BY s.end_date ASC
            ");
        }
        
        // Pagamentos pendentes
        $pendingPayments = [];
        if (
            dbTableExists($db, 'payments') &&
            dbTableExists($db, 'subscriptions') &&
            dbTableExists($db, 'users') &&
            dbTableExists($db, 'plans') &&
            dbColumnExists($db, 'payments', 'subscription_id') &&
            dbColumnExists($db, 'payments', 'status') &&
            dbColumnExists($db, 'payments', 'created_at')
        ) {
            $pendingPayments = dbFetchAll($db, "
                SELECT 
                    p.id,
                    p.amount,
                    p.payment_method,
                    p.created_at,
                    u.name as user_name,
                    u.email as user_email,
                    pl.name as plan_name
                FROM payments p
                JOIN subscriptions s ON p.subscription_id = s.id
                JOIN users u ON s.user_id = u.id
                JOIN plans pl ON s.plan_id = pl.id
                WHERE p.status = 'pending'
                ORDER BY p.created_at DESC
                LIMIT 10
            ");
        }
        
        // Atividade recente
        $recentActivity = getRecentActivity($db, 20);
        
        $dashboard = [
            'stats' => $statsSummary,
            'charts' => [
                'monthly_revenue' => $monthlyRevenue,
                'user_growth' => $userGrowth,
                'subscriptions_by_plan' => $subscriptionsByPlan,
                'payment_methods' => $paymentMethods
            ],
            'lists' => [
                'top_users' => $topUsers,
                'expiring_soon' => $expiringSoon,
                'pending_payments' => $pendingPayments
            ],
            'recent_activity' => $recentActivity
        ];
        
        echo ApiUtils::success($dashboard, 'Dashboard administrativo obtido com sucesso');
        
    } catch (Throwable $e) {
        echo ApiUtils::error('Erro ao obter dashboard administrativo', 500, $e->getMessage());
    }
}

/**
 * Dashboard do usuário
 */
function handleUserDashboard($db) {
    ApiUtils::validateMethod(['GET']);
    ApiUtils::requireAuth();
    
    try {
        $userId = $_SESSION['user_id'];
        
        // Dados do usuário
        $stmt = $db->prepare("
            SELECT 
                id, name, email, points, created_at,
                (SELECT COUNT(*) FROM subscriptions WHERE user_id = ? AND status = 'active') as active_subscriptions
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$userId, $userId]);
        $user = $stmt->fetch();
        
        // Assinatura ativa
        $stmt = $db->prepare("
            SELECT 
                s.*,
                p.name as plan_name,
                p.description as plan_description,
                p.price,
                p.duration_months,
                p.features,
                p.max_devices,
                p.quality
            FROM subscriptions s
            JOIN plans p ON s.plan_id = p.id
            WHERE s.user_id = ? AND s.status = 'active'
            ORDER BY s.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $activeSubscription = $stmt->fetch();
        
        if ($activeSubscription) {
            // Calcular dias restantes
            $endDate = new DateTime($activeSubscription['end_date']);
            $today = new DateTime();
            $daysRemaining = $today <= $endDate ? $today->diff($endDate)->days : 0;
            $activeSubscription['days_remaining'] = $daysRemaining;
            
            // Converter features
            if (!empty($activeSubscription['features'])) {
                $features = json_decode($activeSubscription['features'], true);
                $activeSubscription['features'] = $features ?: explode(',', $activeSubscription['features']);
            } else {
                $activeSubscription['features'] = [];
            }
        }
        
        // Histórico de pontos recente
        $stmt = $db->prepare("
            SELECT * FROM points_history 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$userId]);
        $pointsHistory = $stmt->fetchAll();
        
        // Decodificar metadata
        foreach ($pointsHistory as &$transaction) {
            if (!empty($transaction['metadata'])) {
                $transaction['metadata'] = json_decode($transaction['metadata'], true);
            }
        }
        
        // Recompensas disponíveis (que o usuário pode resgatar)
        $stmt = $db->prepare("
            SELECT * FROM rewards 
            WHERE status = 'active' 
            AND points_required <= ?
            AND (stock IS NULL OR stock > 0)
            ORDER BY points_required ASC
            LIMIT 5
        ");
        $stmt->execute([$user['points']]);
        $availableRewards = $stmt->fetchAll();
        
        // Verificar recompensas únicas já resgatadas
        foreach ($availableRewards as &$reward) {
            if ($reward['type'] === 'unique') {
                $stmt = $db->prepare("
                    SELECT id FROM user_rewards 
                    WHERE user_id = ? AND reward_id = ?
                ");
                $stmt->execute([$userId, $reward['id']]);
                $alreadyRedeemed = $stmt->fetch();
                
                if ($alreadyRedeemed) {
                    $reward['already_redeemed'] = true;
                }
            }
        }
        
        // Remover recompensas já resgatadas
        $availableRewards = array_filter($availableRewards, function($reward) {
            return !isset($reward['already_redeemed']);
        });
        $availableRewards = array_values($availableRewards);
        
        // Pagamentos recentes
        $stmt = $db->prepare("
            SELECT 
                p.*,
                pl.name as plan_name
            FROM payments p
            JOIN subscriptions s ON p.subscription_id = s.id
            JOIN plans pl ON s.plan_id = pl.id
            WHERE s.user_id = ?
            ORDER BY p.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$userId]);
        $recentPayments = $stmt->fetchAll();
        
        // Posição no ranking
        $stmt = $db->prepare("
            SELECT COUNT(*) + 1 as position
            FROM users 
            WHERE points > ? AND status = 'active'
        ");
        $stmt->execute([$user['points']]);
        $rankingPosition = $stmt->fetch()['position'];
        
        // Total de usuários ativos para calcular percentil
        $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
        $totalUsers = $stmt->fetch()['total'];
        
        $percentile = $totalUsers > 0 ? round((($totalUsers - $rankingPosition + 1) / $totalUsers) * 100, 1) : 0;
        
        // Estatísticas pessoais
        $stmt = $db->prepare("
            SELECT 
                SUM(CASE WHEN points > 0 THEN points ELSE 0 END) as total_earned,
                SUM(CASE WHEN points < 0 THEN ABS(points) ELSE 0 END) as total_spent,
                COUNT(*) as total_transactions
            FROM points_history 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $pointsStats = $stmt->fetch();
        
        $dashboard = [
            'user' => $user,
            'active_subscription' => $activeSubscription,
            'points' => [
                'current_balance' => (int)$user['points'],
                'total_earned' => (int)($pointsStats['total_earned'] ?? 0),
                'total_spent' => (int)($pointsStats['total_spent'] ?? 0),
                'total_transactions' => (int)($pointsStats['total_transactions'] ?? 0),
                'recent_history' => $pointsHistory
            ],
            'ranking' => [
                'position' => $rankingPosition,
                'percentile' => $percentile,
                'total_users' => $totalUsers
            ],
            'available_rewards' => $availableRewards,
            'recent_payments' => $recentPayments
        ];
        
        echo ApiUtils::success($dashboard, 'Dashboard do usuário obtido com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao obter dashboard do usuário');
    }
}

/**
 * Estatísticas gerais
 */
function handleGeneralStats($db) {
    ApiUtils::validateMethod(['GET']);
    ApiUtils::requireAuth();
    
    try {
        $stats = getGeneralStats($db);
        echo ApiUtils::success($stats, 'Estatísticas obtidas com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao obter estatísticas');
    }
}

/**
 * Atividade recente
 */
function handleRecentActivity($db, $input) {
    ApiUtils::validateMethod(['GET']);
    ApiUtils::requireAuth();
    
    try {
        $limit = min($input['limit'] ?? 50, 100);
        $activity = getRecentActivity($db, $limit);
        
        echo ApiUtils::success($activity, 'Atividade recente obtida com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao obter atividade recente');
    }
}

/**
 * Obter notificações
 */
function handleGetNotifications($db, $input) {
    ApiUtils::validateMethod(['GET']);
    ApiUtils::requireAuth();
    
    try {
        $userId = $_SESSION['user_id'];
        $unreadOnly = $input['unread_only'] ?? false;
        
        // Construir query
        $whereClause = "WHERE (user_id = ? OR user_id IS NULL)";
        $params = [$userId];
        
        if ($unreadOnly) {
            $whereClause .= " AND read_at IS NULL";
        }
        
        // Simular tabela de notificações (em produção, criar tabela real)
        $notifications = [];
        
        // Verificar assinatura expirando
        $stmt = $db->prepare("
            SELECT s.end_date, p.name as plan_name
            FROM subscriptions s
            JOIN plans p ON s.plan_id = p.id
            WHERE s.user_id = ? AND s.status = 'active'
            AND s.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ");
        $stmt->execute([$userId]);
        $expiring = $stmt->fetch();
        
        if ($expiring) {
            $endDate = new DateTime($expiring['end_date']);
            $today = new DateTime();
            $daysRemaining = $today->diff($endDate)->days;
            
            $notifications[] = [
                'id' => 'expiring_subscription',
                'type' => 'warning',
                'title' => 'Assinatura expirando',
                'message' => "Sua assinatura {$expiring['plan_name']} expira em {$daysRemaining} dias.",
                'created_at' => date('Y-m-d H:i:s'),
                'read_at' => null,
                'action_url' => '/subscriptions'
            ];
        }
        
        // Verificar pagamentos pendentes
        $stmt = $db->prepare("
            SELECT COUNT(*) as count
            FROM payments p
            JOIN subscriptions s ON p.subscription_id = s.id
            WHERE s.user_id = ? AND p.status = 'pending'
        ");
        $stmt->execute([$userId]);
        $pendingCount = $stmt->fetch()['count'];
        
        if ($pendingCount > 0) {
            $notifications[] = [
                'id' => 'pending_payments',
                'type' => 'info',
                'title' => 'Pagamentos pendentes',
                'message' => "Você tem {$pendingCount} pagamento(s) pendente(s).",
                'created_at' => date('Y-m-d H:i:s'),
                'read_at' => null,
                'action_url' => '/payments'
            ];
        }
        
        // Verificar recompensas disponíveis
        $stmt = $db->prepare("
            SELECT COUNT(*) as count
            FROM rewards 
            WHERE status = 'active' 
            AND points_required <= (SELECT points FROM users WHERE id = ?)
            AND (stock IS NULL OR stock > 0)
        ");
        $stmt->execute([$userId]);
        $availableRewards = $stmt->fetch()['count'];
        
        if ($availableRewards > 0) {
            $notifications[] = [
                'id' => 'available_rewards',
                'type' => 'success',
                'title' => 'Recompensas disponíveis',
                'message' => "Você pode resgatar {$availableRewards} recompensa(s) com seus pontos.",
                'created_at' => date('Y-m-d H:i:s'),
                'read_at' => null,
                'action_url' => '/rewards'
            ];
        }
        
        echo ApiUtils::success($notifications, 'Notificações obtidas com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao obter notificações');
    }
}

/**
 * Marcar notificação como lida
 */
function handleMarkNotificationRead($db, $input) {
    ApiUtils::validateMethod(['POST']);
    ApiUtils::requireAuth();
    ApiUtils::validateRequired($input, ['notification_id']);
    
    try {
        // Em produção, atualizar tabela de notificações
        // Por enquanto, apenas retornar sucesso
        
        echo ApiUtils::success(null, 'Notificação marcada como lida');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao marcar notificação como lida');
    }
}

/**
 * Obter estatísticas gerais
 */
function getGeneralStats($db) {
    if (!dbTableExists($db, 'users')) {
        return [
            'users' => [
                'total' => 0,
                'new_today' => 0,
                'active_subscriptions' => 0,
                'conversion_rate' => 0
            ],
            'revenue' => [
                'total' => 0,
                'today' => 0
            ],
            'points' => [
                'total_in_circulation' => 0,
                'rewards_redeemed_today' => 0
            ]
        ];
    }

    // Total de usuários
    $usersWhere = dbColumnExists($db, 'users', 'status') ? "WHERE status = 'active'" : '';
    $totalUsers = (int)dbFetchValue($db, "SELECT COUNT(*) FROM users {$usersWhere}", [], 0);
    
    // Usuários novos hoje
    $newUsersToday = 0;
    if (dbColumnExists($db, 'users', 'created_at')) {
        $newUsersToday = (int)dbFetchValue($db, "SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()", [], 0);
    }
    
    // Total de assinaturas ativas
    $activeSubscriptions = 0;
    if (dbTableExists($db, 'subscriptions')) {
        $subWhere = dbColumnExists($db, 'subscriptions', 'status') ? "WHERE status = 'active'" : '';
        $activeSubscriptions = (int)dbFetchValue($db, "SELECT COUNT(*) FROM subscriptions {$subWhere}", [], 0);
    }
    
    // Receita total
    $totalRevenue = 0;
    if (dbTableExists($db, 'payments') && dbColumnExists($db, 'payments', 'amount')) {
        $revWhere = dbColumnExists($db, 'payments', 'status') ? "WHERE status = 'completed'" : '';
        $totalRevenue = (float)dbFetchValue($db, "SELECT COALESCE(SUM(amount), 0) FROM payments {$revWhere}", [], 0);
    }
    
    // Receita hoje
    $revenueToday = 0;
    if (
        dbTableExists($db, 'payments') &&
        dbColumnExists($db, 'payments', 'amount') &&
        dbColumnExists($db, 'payments', 'created_at') &&
        dbColumnExists($db, 'payments', 'status')
    ) {
        $revenueToday = (float)dbFetchValue(
            $db,
            "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed' AND DATE(created_at) = CURDATE()",
            [],
            0
        );
    }
    
    // Total de pontos em circulação
    $totalPoints = 0;
    if (dbColumnExists($db, 'users', 'points')) {
        $pointsWhere = dbColumnExists($db, 'users', 'status') ? "WHERE status = 'active'" : '';
        $totalPoints = (int)dbFetchValue($db, "SELECT COALESCE(SUM(points), 0) FROM users {$pointsWhere}", [], 0);
    }
    
    // Recompensas resgatadas hoje
    $rewardsToday = 0;
    if (dbTableExists($db, 'user_rewards') && dbColumnExists($db, 'user_rewards', 'created_at')) {
        $rewardsToday = (int)dbFetchValue($db, "SELECT COUNT(*) FROM user_rewards WHERE DATE(created_at) = CURDATE()", [], 0);
    }
    
    // Taxa de conversão (usuários com assinatura ativa)
    $conversionRate = $totalUsers > 0 ? ($activeSubscriptions / $totalUsers) * 100 : 0;
    
    return [
        'users' => [
            'total' => $totalUsers,
            'new_today' => $newUsersToday,
            'active_subscriptions' => $activeSubscriptions,
            'conversion_rate' => round($conversionRate, 2)
        ],
        'revenue' => [
            'total' => $totalRevenue,
            'today' => $revenueToday
        ],
        'points' => [
            'total_in_circulation' => $totalPoints,
            'rewards_redeemed_today' => $rewardsToday
        ]
    ];
}

/**
 * Obter atividade recente
 */
function getRecentActivity($db, $limit = 50) {
    $activities = [];
    
    // Novos usuários
    if (dbTableExists($db, 'users') && dbColumnExists($db, 'users', 'created_at')) {
        $activities = array_merge(
            $activities,
            dbFetchAll(
                $db,
                "
                    SELECT 
                        'user_registered' as type,
                        name as user_name,
                        created_at,
                        'Novo usuário registrado' as description
                    FROM users 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    ORDER BY created_at DESC
                    LIMIT ?
                ",
                [intval($limit / 4)]
            )
        );
    }
    
    // Novas assinaturas
    if (
        dbTableExists($db, 'subscriptions') &&
        dbTableExists($db, 'users') &&
        dbTableExists($db, 'plans') &&
        dbColumnExists($db, 'subscriptions', 'created_at') &&
        dbColumnExists($db, 'subscriptions', 'user_id') &&
        dbColumnExists($db, 'subscriptions', 'plan_id')
    ) {
        $activities = array_merge(
            $activities,
            dbFetchAll(
                $db,
                "
                    SELECT 
                        'subscription_created' as type,
                        u.name as user_name,
                        s.created_at,
                        CONCAT('Nova assinatura: ', p.name) as description
                    FROM subscriptions s
                    JOIN users u ON s.user_id = u.id
                    JOIN plans p ON s.plan_id = p.id
                    WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    ORDER BY s.created_at DESC
                    LIMIT ?
                ",
                [intval($limit / 4)]
            )
        );
    }
    
    // Pagamentos aprovados
    if (
        dbTableExists($db, 'payments') &&
        dbTableExists($db, 'subscriptions') &&
        dbTableExists($db, 'users') &&
        dbColumnExists($db, 'payments', 'amount') &&
        dbColumnExists($db, 'payments', 'status') &&
        dbColumnExists($db, 'payments', 'subscription_id')
    ) {
        $dateColumn = dbColumnExists($db, 'payments', 'updated_at') ? 'p.updated_at' : (dbColumnExists($db, 'payments', 'created_at') ? 'p.created_at' : null);
        if ($dateColumn !== null) {
            $activities = array_merge(
                $activities,
                dbFetchAll(
                    $db,
                    "
                        SELECT 
                            'payment_completed' as type,
                            u.name as user_name,
                            {$dateColumn} as created_at,
                            CONCAT('Pagamento aprovado: R$ ', FORMAT(p.amount, 2)) as description
                        FROM payments p
                        JOIN subscriptions s ON p.subscription_id = s.id
                        JOIN users u ON s.user_id = u.id
                        WHERE p.status = 'completed' 
                        AND {$dateColumn} >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                        ORDER BY {$dateColumn} DESC
                        LIMIT ?
                    ",
                    [intval($limit / 4)]
                )
            );
        }
    }
    
    // Recompensas resgatadas
    if (
        dbTableExists($db, 'user_rewards') &&
        dbTableExists($db, 'users') &&
        dbTableExists($db, 'rewards') &&
        dbColumnExists($db, 'user_rewards', 'created_at') &&
        dbColumnExists($db, 'user_rewards', 'user_id') &&
        dbColumnExists($db, 'user_rewards', 'reward_id')
    ) {
        $activities = array_merge(
            $activities,
            dbFetchAll(
                $db,
                "
                    SELECT 
                        'reward_redeemed' as type,
                        u.name as user_name,
                        ur.created_at,
                        CONCAT('Recompensa resgatada: ', r.name) as description
                    FROM user_rewards ur
                    JOIN users u ON ur.user_id = u.id
                    JOIN rewards r ON ur.reward_id = r.id
                    WHERE ur.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    ORDER BY ur.created_at DESC
                    LIMIT ?
                ",
                [intval($limit / 4)]
            )
        );
    }
    
    // Ordenar por data
    usort($activities, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    return array_slice($activities, 0, $limit);
}

function dbTableExists($db, $tableName) {
    static $cache = [];
    $key = (string)$tableName;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $stmt->execute([$key]);
        $cache[$key] = ((int)$stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        $cache[$key] = false;
    }

    return $cache[$key];
}

function dbColumnExists($db, $tableName, $columnName) {
    static $cache = [];
    $key = (string)$tableName . '.' . (string)$columnName;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
        $stmt->execute([(string)$tableName, (string)$columnName]);
        $cache[$key] = ((int)$stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        $cache[$key] = false;
    }

    return $cache[$key];
}

function dbFetchAll($db, $sql, $params = []) {
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll();
        return is_array($result) ? $result : [];
    } catch (Throwable $e) {
        return [];
    }
}

function dbFetchValue($db, $sql, $params = [], $default = 0) {
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return $value === false || $value === null ? $default : $value;
    } catch (Throwable $e) {
        return $default;
    }
}

?>
