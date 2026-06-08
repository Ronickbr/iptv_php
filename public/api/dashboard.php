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
        
        // Receita mensal
        $stmt = $db->query("
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
        $monthlyRevenue = $stmt->fetchAll();
        
        // Crescimento de usuários
        $stmt = $db->query("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as new_users
            FROM users 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month
        ");
        $userGrowth = $stmt->fetchAll();
        
        // Assinaturas por plano
        $stmt = $db->query("
            SELECT 
                p.name as plan_name,
                COUNT(s.id) as subscriptions,
                SUM(p.price) as revenue
            FROM subscriptions s
            JOIN plans p ON s.plan_id = p.id
            WHERE s.status = 'active'
            GROUP BY p.id
            ORDER BY subscriptions DESC
        ");
        $subscriptionsByPlan = $stmt->fetchAll();
        
        // Métodos de pagamento mais usados
        $stmt = $db->query("
            SELECT 
                payment_method,
                COUNT(*) as count,
                SUM(amount) as revenue
            FROM payments 
            WHERE status = 'completed'
            GROUP BY payment_method
            ORDER BY count DESC
        ");
        $paymentMethods = $stmt->fetchAll();
        
        // Usuários mais ativos (por pontos)
        $stmt = $db->query("
            SELECT 
                u.id,
                u.name,
                u.email,
                u.points,
                u.created_at
            FROM users u
            WHERE u.status = 'active'
            ORDER BY u.points DESC
            LIMIT 10
        ");
        $topUsers = $stmt->fetchAll();
        
        // Assinaturas expirando nos próximos 7 dias
        $stmt = $db->query("
            SELECT 
                s.id,
                s.end_date,
                u.name as user_name,
                u.email as user_email,
                p.name as plan_name
            FROM subscriptions s
            JOIN users u ON s.user_id = u.id
            JOIN plans p ON s.plan_id = p.id
            WHERE s.status = 'active'
            AND s.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            ORDER BY s.end_date ASC
        ");
        $expiringSoon = $stmt->fetchAll();
        
        // Pagamentos pendentes
        $stmt = $db->query("
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
        $pendingPayments = $stmt->fetchAll();
        
        // Atividade recente
        $recentActivity = getRecentActivity($db, 20);
        
        $dashboard = [
            'stats' => $stats,
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
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao obter dashboard administrativo');
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
    // Total de usuários
    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
    $totalUsers = $stmt->fetch()['total'];
    
    // Usuários novos hoje
    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE DATE(created_at) = CURDATE()");
    $newUsersToday = $stmt->fetch()['total'];
    
    // Total de assinaturas ativas
    $stmt = $db->query("SELECT COUNT(*) as total FROM subscriptions WHERE status = 'active'");
    $activeSubscriptions = $stmt->fetch()['total'];
    
    // Receita total
    $stmt = $db->query("SELECT SUM(amount) as total FROM payments WHERE status = 'completed'");
    $totalRevenue = $stmt->fetch()['total'] ?? 0;
    
    // Receita hoje
    $stmt = $db->query("
        SELECT SUM(amount) as total 
        FROM payments 
        WHERE status = 'completed' AND DATE(created_at) = CURDATE()
    ");
    $revenueToday = $stmt->fetch()['total'] ?? 0;
    
    // Total de pontos em circulação
    $stmt = $db->query("SELECT SUM(points) as total FROM users WHERE status = 'active'");
    $totalPoints = $stmt->fetch()['total'] ?? 0;
    
    // Recompensas resgatadas hoje
    $stmt = $db->query("SELECT COUNT(*) as total FROM user_rewards WHERE DATE(created_at) = CURDATE()");
    $rewardsToday = $stmt->fetch()['total'];
    
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
    $stmt = $db->prepare("
        SELECT 
            'user_registered' as type,
            name as user_name,
            created_at,
            'Novo usuário registrado' as description
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY created_at DESC
        LIMIT ?
    ");
    $stmt->execute([intval($limit / 4)]);
    $activities = array_merge($activities, $stmt->fetchAll());
    
    // Novas assinaturas
    $stmt = $db->prepare("
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
    ");
    $stmt->execute([intval($limit / 4)]);
    $activities = array_merge($activities, $stmt->fetchAll());
    
    // Pagamentos aprovados
    $stmt = $db->prepare("
        SELECT 
            'payment_completed' as type,
            u.name as user_name,
            p.updated_at as created_at,
            CONCAT('Pagamento aprovado: R$ ', FORMAT(p.amount, 2)) as description
        FROM payments p
        JOIN subscriptions s ON p.subscription_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE p.status = 'completed' 
        AND p.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY p.updated_at DESC
        LIMIT ?
    ");
    $stmt->execute([intval($limit / 4)]);
    $activities = array_merge($activities, $stmt->fetchAll());
    
    // Recompensas resgatadas
    $stmt = $db->prepare("
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
    ");
    $stmt->execute([intval($limit / 4)]);
    $activities = array_merge($activities, $stmt->fetchAll());
    
    // Ordenar por data
    usort($activities, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    return array_slice($activities, 0, $limit);
}

?>