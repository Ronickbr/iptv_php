<?php
/**
 * Endpoint de Gerenciamento de Assinaturas
 * KMKZ IPTV API
 * 
 * Endpoints disponíveis:
 * GET /subscriptions.php?action=list - Listar assinaturas
 * GET /subscriptions.php?action=get&id={id} - Obter assinatura específica
 * POST /subscriptions.php?action=create - Criar assinatura
 * PUT /subscriptions.php?action=update - Atualizar assinatura
 * POST /subscriptions.php?action=cancel&id={id} - Cancelar assinatura
 * POST /subscriptions.php?action=renew&id={id} - Renovar assinatura
 * GET /subscriptions.php?action=user_subscriptions - Assinaturas do usuário logado
 * GET /subscriptions.php?action=stats - Estatísticas de assinaturas (admin)
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
    case 'list':
        handleListSubscriptions($db, $input);
        break;
        
    case 'get':
        handleGetSubscription($db, $input);
        break;
        
    case 'create':
        handleCreateSubscription($db, $input);
        break;
        
    case 'update':
        handleUpdateSubscription($db, $input);
        break;
        
    case 'cancel':
        handleCancelSubscription($db, $input);
        break;
        
    case 'renew':
        handleRenewSubscription($db, $input);
        break;
        
    case 'user_subscriptions':
        handleUserSubscriptions($db);
        break;
        
    case 'stats':
        handleSubscriptionStats($db);
        break;
        
    default:
        echo ApiUtils::error('Ação não encontrada', 404);
        break;
}

/**
 * Listar assinaturas (admin)
 */
function handleListSubscriptions($db, $input) {
    ApiUtils::validateMethod(['GET']);
    ApiUtils::requireAdmin();
    
    try {
        // Parâmetros de paginação
        $pagination = ApiUtils::getPagination(
            $input['page'] ?? 1,
            $input['per_page'] ?? DEFAULT_PAGE_SIZE
        );
        
        // Filtros
        $status = $input['status'] ?? '';
        $planId = $input['plan_id'] ?? '';
        $userId = $input['user_id'] ?? '';
        $search = $input['search'] ?? '';
        
        // Construir query
        $whereConditions = [];
        $params = [];
        
        if (!empty($status)) {
            $whereConditions[] = "s.status = ?";
            $params[] = $status;
        }
        
        if (!empty($planId)) {
            $whereConditions[] = "s.plan_id = ?";
            $params[] = $planId;
        }
        
        if (!empty($userId)) {
            $whereConditions[] = "s.user_id = ?";
            $params[] = $userId;
        }
        
        if (!empty($search)) {
            $whereConditions[] = "(u.name LIKE ? OR u.email LIKE ? OR p.name LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // Contar total
        $countQuery = "
            SELECT COUNT(*) as total 
            FROM subscriptions s
            JOIN users u ON s.user_id = u.id
            JOIN plans p ON s.plan_id = p.id
            {$whereClause}
        ";
        $stmt = $db->prepare($countQuery);
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];
        
        // Buscar assinaturas
        $query = "
            SELECT 
                s.*,
                u.name as user_name,
                u.email as user_email,
                p.name as plan_name,
                p.price as plan_price,
                p.duration_months
            FROM subscriptions s
            JOIN users u ON s.user_id = u.id
            JOIN plans p ON s.plan_id = p.id
            {$whereClause}
            ORDER BY s.created_at DESC
            LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $subscriptions = $stmt->fetchAll();
        
        // Calcular paginação
        $paginationInfo = ApiUtils::calculatePagination($total, $pagination['page'], $pagination['per_page']);
        
        echo ApiUtils::success($subscriptions, 'Assinaturas listadas com sucesso', $paginationInfo);
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao listar assinaturas');
    }
}

/**
 * Obter assinatura específica
 */
function handleGetSubscription($db, $input) {
    ApiUtils::validateMethod(['GET']);
    ApiUtils::requireAuth();
    
    $subscriptionId = $input['id'] ?? null;
    
    if (!$subscriptionId) {
        echo ApiUtils::error('ID da assinatura é obrigatório', 400);
        return;
    }
    
    try {
        // Buscar assinatura
        $stmt = $db->prepare("
            SELECT 
                s.*,
                u.name as user_name,
                u.email as user_email,
                p.name as plan_name,
                p.description as plan_description,
                p.price as plan_price,
                p.duration_months,
                p.features,
                p.max_devices,
                p.quality
            FROM subscriptions s
            JOIN users u ON s.user_id = u.id
            JOIN plans p ON s.plan_id = p.id
            WHERE s.id = ?
        ");
        $stmt->execute([$subscriptionId]);
        $subscription = $stmt->fetch();
        
        if (!$subscription) {
            echo ApiUtils::error('Assinatura não encontrada', 404);
            return;
        }
        
        // Verificar permissão (usuário só pode ver próprias assinaturas)
        if ($_SESSION['user_type'] !== 'admin' && $subscription['user_id'] != $_SESSION['user_id']) {
            echo ApiUtils::error('Acesso negado', 403);
            return;
        }
        
        // Buscar pagamentos relacionados
        $stmt = $db->prepare("
            SELECT * FROM payments 
            WHERE subscription_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$subscriptionId]);
        $payments = $stmt->fetchAll();
        
        // Converter features
        if (!empty($subscription['features'])) {
            $features = json_decode($subscription['features'], true);
            $subscription['features'] = $features ?: explode(',', $subscription['features']);
        } else {
            $subscription['features'] = [];
        }
        
        // Calcular dias restantes
        $endDate = new DateTime($subscription['end_date']);
        $today = new DateTime();
        $daysRemaining = $today <= $endDate ? $today->diff($endDate)->days : 0;
        $subscription['days_remaining'] = $daysRemaining;
        
        $result = [
            'subscription' => $subscription,
            'payments' => $payments
        ];
        
        echo ApiUtils::success($result, 'Assinatura obtida com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao obter assinatura');
    }
}

/**
 * Criar assinatura
 */
function handleCreateSubscription($db, $input) {
    ApiUtils::validateMethod(['POST']);
    ApiUtils::requireAuth();
    ApiUtils::validateRequired($input, ['plan_id']);
    
    $planId = $input['plan_id'];
    $userId = $input['user_id'] ?? $_SESSION['user_id'];
    $paymentMethod = $input['payment_method'] ?? 'pix';
    
    // Apenas admin pode criar assinatura para outros usuários
    if ($_SESSION['user_type'] !== 'admin' && $userId != $_SESSION['user_id']) {
        echo ApiUtils::error('Acesso negado', 403);
        return;
    }
    
    try {
        // Verificar se plano existe e está ativo
        $stmt = $db->prepare("SELECT * FROM plans WHERE id = ? AND status = 'active'");
        $stmt->execute([$planId]);
        $plan = $stmt->fetch();
        
        if (!$plan) {
            echo ApiUtils::error('Plano não encontrado ou inativo', 404);
            return;
        }
        
        // Verificar se usuário existe
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo ApiUtils::error('Usuário não encontrado ou inativo', 404);
            return;
        }
        
        // Verificar se usuário já tem assinatura ativa
        $stmt = $db->prepare("SELECT id FROM subscriptions WHERE user_id = ? AND status = 'active'");
        $stmt->execute([$userId]);
        $activeSubscription = $stmt->fetch();
        
        if ($activeSubscription) {
            echo ApiUtils::error('Usuário já possui uma assinatura ativa', 409);
            return;
        }
        
        // Criar assinatura
        $subscriptionId = createSubscription($db, $userId, $planId, $paymentMethod);
        
        if (!$subscriptionId) {
            echo ApiUtils::error('Erro ao criar assinatura');
            return;
        }
        
        // Log da ação
        ApiUtils::logActivity('subscription_created', [
            'subscription_id' => $subscriptionId,
            'user_id' => $userId,
            'plan_id' => $planId,
            'created_by' => $_SESSION['user_id']
        ]);
        
        echo ApiUtils::success([
            'subscription_id' => $subscriptionId,
            'plan_name' => $plan['name'],
            'duration_months' => $plan['duration_months']
        ], 'Assinatura criada com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao criar assinatura');
    }
}

/**
 * Atualizar assinatura (admin)
 */
function handleUpdateSubscription($db, $input) {
    ApiUtils::validateMethod(['PUT', 'POST']);
    ApiUtils::requireAdmin();
    
    $subscriptionId = $input['id'] ?? null;
    
    if (!$subscriptionId) {
        echo ApiUtils::error('ID da assinatura é obrigatório', 400);
        return;
    }
    
    try {
        // Verificar se assinatura existe
        $stmt = $db->prepare("SELECT * FROM subscriptions WHERE id = ?");
        $stmt->execute([$subscriptionId]);
        $subscription = $stmt->fetch();
        
        if (!$subscription) {
            echo ApiUtils::error('Assinatura não encontrada', 404);
            return;
        }
        
        // Preparar dados para atualização
        $updateFields = [];
        $params = [];
        
        $allowedFields = ['status', 'start_date', 'end_date', 'auto_renew', 'devices_used'];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                if ($field === 'auto_renew') {
                    $updateFields[] = "{$field} = ?";
                    $params[] = $input[$field] ? 1 : 0;
                } else {
                    $updateFields[] = "{$field} = ?";
                    $params[] = ApiUtils::sanitize($input[$field]);
                }
            }
        }
        
        if (empty($updateFields)) {
            echo ApiUtils::error('Nenhum campo para atualizar', 400);
            return;
        }
        
        // Atualizar assinatura
        $params[] = $subscriptionId;
        $query = "UPDATE subscriptions SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        // Log da ação
        ApiUtils::logActivity('subscription_updated', [
            'subscription_id' => $subscriptionId,
            'updated_fields' => array_keys($input),
            'updated_by' => $_SESSION['user_id']
        ]);
        
        echo ApiUtils::success(null, 'Assinatura atualizada com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao atualizar assinatura');
    }
}

/**
 * Cancelar assinatura
 */
function handleCancelSubscription($db, $input) {
    ApiUtils::validateMethod(['POST']);
    ApiUtils::requireAuth();
    
    $subscriptionId = $input['id'] ?? null;
    
    if (!$subscriptionId) {
        echo ApiUtils::error('ID da assinatura é obrigatório', 400);
        return;
    }
    
    try {
        // Buscar assinatura
        $stmt = $db->prepare("SELECT * FROM subscriptions WHERE id = ?");
        $stmt->execute([$subscriptionId]);
        $subscription = $stmt->fetch();
        
        if (!$subscription) {
            echo ApiUtils::error('Assinatura não encontrada', 404);
            return;
        }
        
        // Verificar permissão
        if ($_SESSION['user_type'] !== 'admin' && $subscription['user_id'] != $_SESSION['user_id']) {
            echo ApiUtils::error('Acesso negado', 403);
            return;
        }
        
        // Verificar se pode ser cancelada
        if ($subscription['status'] === 'cancelled') {
            echo ApiUtils::error('Assinatura já está cancelada', 400);
            return;
        }
        
        // Cancelar assinatura
        $stmt = $db->prepare("UPDATE subscriptions SET status = 'cancelled', auto_renew = 0 WHERE id = ?");
        $stmt->execute([$subscriptionId]);
        
        // Log da ação
        ApiUtils::logActivity('subscription_cancelled', [
            'subscription_id' => $subscriptionId,
            'user_id' => $subscription['user_id'],
            'cancelled_by' => $_SESSION['user_id']
        ]);
        
        echo ApiUtils::success(null, 'Assinatura cancelada com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao cancelar assinatura');
    }
}

/**
 * Renovar assinatura
 */
function handleRenewSubscription($db, $input) {
    ApiUtils::validateMethod(['POST']);
    ApiUtils::requireAuth();
    
    $subscriptionId = $input['id'] ?? null;
    $paymentMethod = $input['payment_method'] ?? 'pix';
    
    if (!$subscriptionId) {
        echo ApiUtils::error('ID da assinatura é obrigatório', 400);
        return;
    }
    
    try {
        // Buscar assinatura
        $stmt = $db->prepare("
            SELECT s.*, p.duration_months, p.price 
            FROM subscriptions s 
            JOIN plans p ON s.plan_id = p.id 
            WHERE s.id = ?
        ");
        $stmt->execute([$subscriptionId]);
        $subscription = $stmt->fetch();
        
        if (!$subscription) {
            echo ApiUtils::error('Assinatura não encontrada', 404);
            return;
        }
        
        // Verificar permissão
        if ($_SESSION['user_type'] !== 'admin' && $subscription['user_id'] != $_SESSION['user_id']) {
            echo ApiUtils::error('Acesso negado', 403);
            return;
        }
        
        // Calcular nova data de término
        $currentEndDate = new DateTime($subscription['end_date']);
        $today = new DateTime();
        
        // Se a assinatura ainda está ativa, renovar a partir da data de término
        // Se expirou, renovar a partir de hoje
        $startDate = $currentEndDate > $today ? $currentEndDate : $today;
        $newEndDate = clone $startDate;
        $newEndDate->add(new DateInterval('P' . $subscription['duration_months'] . 'M'));
        
        // Atualizar assinatura
        $stmt = $db->prepare("
            UPDATE subscriptions 
            SET status = 'active', end_date = ?, auto_renew = 1 
            WHERE id = ?
        ");
        $stmt->execute([$newEndDate->format('Y-m-d'), $subscriptionId]);
        
        // Criar registro de pagamento
        $stmt = $db->prepare("
            INSERT INTO payments (subscription_id, amount, payment_method, status) 
            VALUES (?, ?, ?, 'pending')
        ");
        $stmt->execute([$subscriptionId, $subscription['price'], $paymentMethod]);
        
        $paymentId = $db->lastInsertId();
        
        // Conceder pontos por renovação
        awardPointsByRule($db, $subscription['user_id'], 'subscription', $subscriptionId, [
            'plan_id' => $subscription['plan_id'],
            'plan_price' => $subscription['price'],
            'duration_months' => $subscription['duration_months'],
            'renewal' => true
        ]);
        
        // Log da ação
        ApiUtils::logActivity('subscription_renewed', [
            'subscription_id' => $subscriptionId,
            'payment_id' => $paymentId,
            'new_end_date' => $newEndDate->format('Y-m-d'),
            'renewed_by' => $_SESSION['user_id']
        ]);
        
        echo ApiUtils::success([
            'subscription_id' => $subscriptionId,
            'payment_id' => $paymentId,
            'new_end_date' => $newEndDate->format('Y-m-d')
        ], 'Assinatura renovada com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao renovar assinatura');
    }
}

/**
 * Assinaturas do usuário logado
 */
function handleUserSubscriptions($db) {
    ApiUtils::validateMethod(['GET']);
    ApiUtils::requireAuth();
    
    try {
        // Buscar assinaturas do usuário
        $stmt = $db->prepare("
            SELECT 
                s.*,
                p.name as plan_name,
                p.description as plan_description,
                p.price as plan_price,
                p.duration_months,
                p.features,
                p.max_devices,
                p.quality
            FROM subscriptions s
            JOIN plans p ON s.plan_id = p.id
            WHERE s.user_id = ?
            ORDER BY s.created_at DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $subscriptions = $stmt->fetchAll();
        
        // Para cada assinatura, calcular dias restantes e converter features
        foreach ($subscriptions as &$subscription) {
            // Converter features
            if (!empty($subscription['features'])) {
                $features = json_decode($subscription['features'], true);
                $subscription['features'] = $features ?: explode(',', $subscription['features']);
            } else {
                $subscription['features'] = [];
            }
            
            // Calcular dias restantes
            $endDate = new DateTime($subscription['end_date']);
            $today = new DateTime();
            $daysRemaining = $today <= $endDate ? $today->diff($endDate)->days : 0;
            $subscription['days_remaining'] = $daysRemaining;
            
            // Status mais descritivo
            if ($subscription['status'] === 'active' && $daysRemaining <= 0) {
                $subscription['status_description'] = 'Expirada';
            } elseif ($subscription['status'] === 'active') {
                $subscription['status_description'] = 'Ativa';
            } else {
                $subscription['status_description'] = ucfirst($subscription['status']);
            }
        }
        
        echo ApiUtils::success($subscriptions, 'Assinaturas obtidas com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao obter assinaturas');
    }
}

/**
 * Estatísticas de assinaturas (admin)
 */
function handleSubscriptionStats($db) {
    ApiUtils::validateMethod(['GET']);
    ApiUtils::requireAdmin();
    
    try {
        // Total de assinaturas
        $stmt = $db->query("SELECT COUNT(*) as total FROM subscriptions");
        $totalSubscriptions = $stmt->fetch()['total'];
        
        // Assinaturas por status
        $stmt = $db->query("SELECT status, COUNT(*) as count FROM subscriptions GROUP BY status");
        $subscriptionsByStatus = $stmt->fetchAll();
        
        // Assinaturas ativas
        $stmt = $db->query("SELECT COUNT(*) as total FROM subscriptions WHERE status = 'active'");
        $activeSubscriptions = $stmt->fetch()['total'];
        
        // Receita total
        $stmt = $db->query("
            SELECT SUM(p.price) as total_revenue
            FROM subscriptions s
            JOIN plans p ON s.plan_id = p.id
            WHERE s.status = 'active'
        ");
        $totalRevenue = $stmt->fetch()['total_revenue'] ?? 0;
        
        // Receita mensal
        $stmt = $db->query("
            SELECT 
                DATE_FORMAT(s.created_at, '%Y-%m') as month,
                SUM(p.price) as revenue,
                COUNT(s.id) as subscriptions
            FROM subscriptions s
            JOIN plans p ON s.plan_id = p.id
            WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(s.created_at, '%Y-%m')
            ORDER BY month
        ");
        $monthlyRevenue = $stmt->fetchAll();
        
        // Assinaturas expirando nos próximos 30 dias
        $stmt = $db->query("
            SELECT COUNT(*) as total 
            FROM subscriptions 
            WHERE status = 'active' 
            AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ");
        $expiringSoon = $stmt->fetch()['total'];
        
        // Taxa de renovação
        $stmt = $db->query("
            SELECT 
                COUNT(CASE WHEN auto_renew = 1 THEN 1 END) as auto_renew_count,
                COUNT(*) as total_active
            FROM subscriptions 
            WHERE status = 'active'
        ");
        $renewalData = $stmt->fetch();
        $renewalRate = $renewalData['total_active'] > 0 ? 
            ($renewalData['auto_renew_count'] / $renewalData['total_active']) * 100 : 0;
        
        // Planos mais populares
        $stmt = $db->query("
            SELECT 
                p.name,
                COUNT(s.id) as subscriptions,
                SUM(p.price) as revenue
            FROM subscriptions s
            JOIN plans p ON s.plan_id = p.id
            WHERE s.status = 'active'
            GROUP BY p.id
            ORDER BY subscriptions DESC
            LIMIT 5
        ");
        $popularPlans = $stmt->fetchAll();
        
        $stats = [
            'total_subscriptions' => $totalSubscriptions,
            'active_subscriptions' => $activeSubscriptions,
            'subscriptions_by_status' => $subscriptionsByStatus,
            'total_revenue' => $totalRevenue,
            'monthly_revenue' => $monthlyRevenue,
            'expiring_soon' => $expiringSoon,
            'renewal_rate' => round($renewalRate, 2),
            'popular_plans' => $popularPlans
        ];
        
        echo ApiUtils::success($stats, 'Estatísticas obtidas com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao obter estatísticas');
    }
}

?>