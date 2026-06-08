<?php
/**
 * Endpoint de Gerenciamento de Pontos e Recompensas
 * KMKZ IPTV API
 * 
 * Endpoints disponíveis:
 * GET /points.php?action=balance - Saldo de pontos do usuário
 * GET /points.php?action=history - Histórico de pontos
 * GET /points.php?action=rules - Regras de pontuação
 * POST /points.php?action=award - Conceder pontos (admin)
 * POST /points.php?action=deduct - Deduzir pontos (admin)
 * GET /points.php?action=leaderboard - Ranking de usuários
 * GET /points.php?action=rewards - Listar recompensas
 * POST /points.php?action=redeem - Resgatar recompensa
 * GET /points.php?action=user_rewards - Recompensas do usuário
 * GET /points.php?action=stats - Estatísticas de pontos (admin)
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
    case 'balance':
        handleGetBalance($db);
        break;
        
    case 'history':
        handleGetHistory($db, $input);
        break;
        
    case 'rules':
        handleGetRules($db);
        break;
        
    case 'award':
        handleAwardPoints($db, $input);
        break;
        
    case 'deduct':
        handleDeductPoints($db, $input);
        break;
        
    case 'leaderboard':
        handleGetLeaderboard($db, $input);
        break;
        
    case 'rewards':
        handleGetRewards($db, $input);
        break;
        
    case 'redeem':
        handleRedeemReward($db, $input);
        break;
        
    case 'user_rewards':
        handleGetUserRewards($db);
        break;
        
    case 'stats':
        handleGetStats($db);
        break;
        
    case 'create_reward':
        handleCreateReward($db, $input);
        break;
        
    case 'update_reward':
        handleUpdateReward($db, $input);
        break;
        
    case 'delete_reward':
        handleDeleteReward($db, $input);
        break;
        
    case 'get_reward':
        handleGetReward($db, $input);
        break;
        
    default:
        echo ApiUtils::error('Ação não encontrada', 404);
        break;
}

/**
 * Obter saldo de pontos do usuário
 */
function handleGetBalance($db) {
    ApiUtils::validateMethod(['GET']);
    ApiUtils::requireAuth();
    
    try {
        // Buscar saldo atual
        $stmt = $db->prepare("SELECT points FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo ApiUtils::error('Usuário não encontrado', 404);
            return;
        }
        
        // Buscar últimas transações
        $stmt = $db->prepare("
            SELECT * FROM points_history 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $recentTransactions = $stmt->fetchAll();
        
        // Buscar total de pontos ganhos e gastos
        $stmt = $db->prepare("
            SELECT 
                SUM(CASE WHEN points > 0 THEN points ELSE 0 END) as total_earned,
                SUM(CASE WHEN points < 0 THEN ABS(points) ELSE 0 END) as total_spent
            FROM points_history 
            WHERE user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $totals = $stmt->fetch();
        
        $result = [
            'current_balance' => (int)$user['points'],
            'total_earned' => (int)($totals['total_earned'] ?? 0),
            'total_spent' => (int)($totals['total_spent'] ?? 0),
            'recent_transactions' => $recentTransactions
        ];
        
        echo ApiUtils::success($result, 'Saldo obtido com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao obter saldo');
    }
}

/**
 * Obter histórico de pontos
 */
function handleGetHistory($db, $input) {
    ApiUtils::validateMethod(['GET']);
    ApiUtils::requireAuth();
    
    try {
        // Parâmetros de paginação
        $pagination = ApiUtils::getPagination(
            $input['page'] ?? 1,
            $input['per_page'] ?? DEFAULT_PAGE_SIZE
        );
        
        // Filtros
        $type = $input['type'] ?? '';
        $dateFrom = $input['date_from'] ?? '';
        $dateTo = $input['date_to'] ?? '';
        
        // Construir query
        $whereConditions = ['user_id = ?'];
        $params = [$_SESSION['user_id']];
        
        if (!empty($type)) {
            $whereConditions[] = "type = ?";
            $params[] = $type;
        }
        
        if (!empty($dateFrom)) {
            $whereConditions[] = "DATE(created_at) >= ?";
            $params[] = $dateFrom;
        }
        
        if (!empty($dateTo)) {
            $whereConditions[] = "DATE(created_at) <= ?";
            $params[] = $dateTo;
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        
        // Contar total
        $countQuery = "SELECT COUNT(*) as total FROM points_history {$whereClause}";
        $stmt = $db->prepare($countQuery);
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];
        
        // Buscar histórico
        $query = "
            SELECT * FROM points_history 
            {$whereClause}
            ORDER BY created_at DESC
            LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $history = $stmt->fetchAll();
        
        // Decodificar metadata
        foreach ($history as &$transaction) {
            if (!empty($transaction['metadata'])) {
                $transaction['metadata'] = json_decode($transaction['metadata'], true);
            }
        }
        
        // Calcular paginação
        $paginationInfo = ApiUtils::calculatePagination($total, $pagination['page'], $pagination['per_page']);
        
        echo ApiUtils::success($history, 'Histórico obtido com sucesso', $paginationInfo);
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao obter histórico');
    }
}

/**
 * Obter regras de pontuação
 */
function handleGetRules($db) {
    ApiUtils::validateMethod(['GET']);
    ApiUtils::requireAuth();
    
    try {
        // Buscar regras ativas
        $stmt = $db->query("
            SELECT * FROM points_rules 
            WHERE status = 'active' 
            ORDER BY points DESC
        ");
        $rules = $stmt->fetchAll();
        
        // Decodificar conditions
        foreach ($rules as &$rule) {
            if (!empty($rule['conditions'])) {
                $rule['conditions'] = json_decode($rule['conditions'], true);
            }
        }
        
        echo ApiUtils::success($rules, 'Regras obtidas com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao obter regras');
    }
}

/**
 * Conceder pontos (admin)
 */
function handleAwardPoints($db, $input) {
    ApiUtils::validateMethod(['POST']);
    ApiUtils::requireAdmin();
    ApiUtils::validateRequired($input, ['user_id', 'points', 'description']);
    
    $userId = $input['user_id'];
    $points = $input['points'];
    $description = $input['description'];
    $type = $input['type'] ?? 'manual';
    $metadata = $input['metadata'] ?? [];
    
    try {
        // Verificar se usuário existe
        $stmt = $db->prepare("SELECT id, points FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo ApiUtils::error('Usuário não encontrado', 404);
            return;
        }
        
        // Validar pontos
        if (!is_numeric($points) || $points <= 0) {
            echo ApiUtils::error('Quantidade de pontos inválida', 400);
            return;
        }
        
        // Conceder pontos
        $success = awardPoints($db, $userId, $points, $type, $description, $metadata);
        
        if (!$success) {
            echo ApiUtils::error('Erro ao conceder pontos');
            return;
        }
        
        // Log da ação
        ApiUtils::logActivity('points_awarded', [
            'user_id' => $userId,
            'points' => $points,
            'type' => $type,
            'description' => $description,
            'awarded_by' => $_SESSION['user_id']
        ]);
        
        echo ApiUtils::success(null, 'Pontos concedidos com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao conceder pontos');
    }
}

/**
 * Deduzir pontos (admin)
 */
function handleDeductPoints($db, $input) {
    ApiUtils::validateMethod(['POST']);
    ApiUtils::requireAdmin();
    ApiUtils::validateRequired($input, ['user_id', 'points', 'description']);
    
    $userId = $input['user_id'];
    $points = $input['points'];
    $description = $input['description'];
    $type = $input['type'] ?? 'manual';
    $metadata = $input['metadata'] ?? [];
    
    try {
        // Verificar se usuário existe
        $stmt = $db->prepare("SELECT id, points FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo ApiUtils::error('Usuário não encontrado', 404);
            return;
        }
        
        // Validar pontos
        if (!is_numeric($points) || $points <= 0) {
            echo ApiUtils::error('Quantidade de pontos inválida', 400);
            return;
        }
        
        // Verificar se usuário tem pontos suficientes
        if ($user['points'] < $points) {
            echo ApiUtils::error('Usuário não possui pontos suficientes', 400);
            return;
        }
        
        // Deduzir pontos
        $success = deductPoints($db, $userId, $points, $type, $description, $metadata);
        
        if (!$success) {
            echo ApiUtils::error('Erro ao deduzir pontos');
            return;
        }
        
        // Log da ação
        ApiUtils::logActivity('points_deducted', [
            'user_id' => $userId,
            'points' => $points,
            'type' => $type,
            'description' => $description,
            'deducted_by' => $_SESSION['user_id']
        ]);
        
        echo ApiUtils::success(null, 'Pontos deduzidos com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao deduzir pontos');
    }
}

/**
 * Obter ranking de usuários
 */
function handleGetLeaderboard($db, $input) {
    ApiUtils::validateMethod(['GET']);
    ApiUtils::requireAuth();
    
    try {
        $limit = min($input['limit'] ?? 50, 100); // Máximo 100
        $period = $input['period'] ?? 'all'; // all, month, week
        
        // Construir query baseada no período
        $whereClause = "WHERE u.status = 'active'";
        
        if ($period === 'month') {
            $whereClause .= " AND ph.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        } elseif ($period === 'week') {
            $whereClause .= " AND ph.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
        }
        
        if ($period === 'all') {
            // Ranking por pontos totais
            $query = "
                SELECT 
                    u.id,
                    u.name,
                    u.email,
                    u.points,
                    u.created_at as member_since
                FROM users u
                {$whereClause}
                ORDER BY u.points DESC
                LIMIT {$limit}
            ";
        } else {
            // Ranking por pontos do período
            $query = "
                SELECT 
                    u.id,
                    u.name,
                    u.email,
                    u.points as total_points,
                    SUM(CASE WHEN ph.points > 0 THEN ph.points ELSE 0 END) as period_points,
                    u.created_at as member_since
                FROM users u
                LEFT JOIN points_history ph ON u.id = ph.user_id
                {$whereClause}
                GROUP BY u.id
                ORDER BY period_points DESC
                LIMIT {$limit}
            ";
        }
        
        $stmt = $db->query($query);
        $leaderboard = $stmt->fetchAll();
        
        // Adicionar posição no ranking
        foreach ($leaderboard as $index => &$user) {
            $user['position'] = $index + 1;
            
            // Remover email para privacidade (exceto próprio usuário)
            if ($user['id'] != $_SESSION['user_id']) {
                $user['email'] = substr($user['email'], 0, 3) . '***@' . 
                    substr($user['email'], strpos($user['email'], '@') + 1);
            }
        }
        
        // Buscar posição do usuário atual se não estiver no top
        $userPosition = null;
        foreach ($leaderboard as $user) {
            if ($user['id'] == $_SESSION['user_id']) {
                $userPosition = $user;
                break;
            }
        }
        
        if (!$userPosition) {
            // Buscar posição do usuário atual
            if ($period === 'all') {
                $stmt = $db->prepare("
                    SELECT COUNT(*) + 1 as position
                    FROM users 
                    WHERE points > (SELECT points FROM users WHERE id = ?) 
                    AND status = 'active'
                ");
            } else {
                $stmt = $db->prepare("
                    SELECT COUNT(*) + 1 as position
                    FROM (
                        SELECT u.id, SUM(CASE WHEN ph.points > 0 THEN ph.points ELSE 0 END) as period_points
                        FROM users u
                        LEFT JOIN points_history ph ON u.id = ph.user_id
                        WHERE u.status = 'active'
                        " . ($period === 'month' ? "AND ph.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)" : "AND ph.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)") . "
                        GROUP BY u.id
                        HAVING period_points > (
                            SELECT SUM(CASE WHEN ph2.points > 0 THEN ph2.points ELSE 0 END)
                            FROM points_history ph2
                            WHERE ph2.user_id = ?
                            " . ($period === 'month' ? "AND ph2.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)" : "AND ph2.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)") . "
                        )
                    ) as ranked
                ");
            }
            $stmt->execute([$_SESSION['user_id']]);
            $position = $stmt->fetch()['position'];
            
            // Buscar dados do usuário atual
            $stmt = $db->prepare("SELECT id, name, email, points FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $currentUser = $stmt->fetch();
            
            if ($currentUser) {
                $userPosition = [
                    'id' => $currentUser['id'],
                    'name' => $currentUser['name'],
                    'email' => $currentUser['email'],
                    'points' => $currentUser['points'],
                    'position' => $position
                ];
            }
        }
        
        $result = [
            'leaderboard' => $leaderboard,
            'user_position' => $userPosition,
            'period' => $period,
            'total_users' => count($leaderboard)
        ];
        
        echo ApiUtils::success($result, 'Ranking obtido com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao obter ranking');
    }
}

/**
 * Listar recompensas
 */
function handleGetRewards($db, $input) {
    ApiUtils::validateMethod(['GET']);
    
    try {
        // Verificar se é admin para mostrar todas as recompensas
        $isAdmin = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
        
        // Filtros
        $category = $input['category'] ?? '';
        
        // Construir query
        $whereConditions = [];
        $params = [];
        
        if (!empty($category)) {
            $whereConditions[] = "reward_type = ?";
            $params[] = $category;
        }
        
        // Para usuários não-admin, filtrar apenas recompensas ativas e disponíveis
        if (!$isAdmin) {
            $whereConditions[] = "is_active = 1";
            $whereConditions[] = "(max_redemptions IS NULL OR current_redemptions < max_redemptions)";
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // Buscar recompensas
        $query = "SELECT * FROM rewards {$whereClause} ORDER BY points_required ASC";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $rewards = $stmt->fetchAll();
        
        // Mapear dados para o formato esperado pelo frontend
        foreach ($rewards as &$reward) {
            // Mapear tipos de recompensa
            $rewardTypeMap = [
                'gift' => 'produto',
                'discount' => 'desconto',
                'free_month' => 'mensalidade grátis',
                'upgrade' => 'upgrade'
            ];
            
            $reward['reward_type'] = $rewardTypeMap[$reward['reward_type']] ?? $reward['reward_type'];
            $reward['stock'] = $reward['max_redemptions'];
            $reward['value'] = $reward['reward_value'] ?? 0;
            
            // Se não é admin, verificar se usuário pode resgatar
            if (!$isAdmin && isset($_SESSION['user_id'])) {
                $stmt = $db->prepare("SELECT points FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $userPoints = $stmt->fetch()['points'] ?? 0;
                
                $reward['can_redeem'] = $userPoints >= $reward['points_required'];
                
                // Verificar se já resgatou (para recompensas limitadas)
                if ($reward['max_redemptions'] !== null) {
                    $stmt = $db->prepare("
                        SELECT COUNT(*) as count FROM user_rewards 
                        WHERE user_id = ? AND reward_id = ?
                    ");
                    $stmt->execute([$_SESSION['user_id'], $reward['id']]);
                    $userRedemptions = $stmt->fetch()['count'];
                    
                    if ($userRedemptions > 0) {
                        $reward['can_redeem'] = false;
                        $reward['already_redeemed'] = true;
                    }
                }
            }
        }
        
        echo ApiUtils::success($rewards, 'Recompensas obtidas com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao obter recompensas: ' . $e->getMessage());
    }
}

/**
 * Resgatar recompensa
 */
function handleRedeemReward($db, $input) {
    ApiUtils::validateMethod(['POST']);
    ApiUtils::requireAuth();
    ApiUtils::validateRequired($input, ['reward_id']);
    
    $rewardId = (int)$input['reward_id'];
    $userId = $_SESSION['user_id'];
    
    try {
        $result = redeemRewardWithResult($db, $userId, $rewardId);
        if (empty($result['success'])) {
            echo ApiUtils::error($result['message'] ?? 'Erro ao resgatar recompensa', 400);
            return;
        }

        $user = getUserById($db, $userId);
        $reward = getReward($db, $rewardId);

        echo ApiUtils::success([
            'reward_id' => $rewardId,
            'reward_name' => $reward['name'] ?? null,
            'remaining_points' => (int)($user['points'] ?? 0)
        ], $result['message'] ?? 'Recompensa resgatada com sucesso');
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao resgatar recompensa');
    }
}

/**
 * Obter recompensas do usuário
 */
function handleGetUserRewards($db) {
    ApiUtils::validateMethod(['GET']);
    ApiUtils::requireAuth();
    
    try {
        // Buscar recompensas do usuário
        $stmt = $db->prepare("
            SELECT 
                ur.*,
                r.name as reward_name,
                r.description as reward_description,
                r.reward_type,
                r.reward_value
            FROM user_rewards ur
            JOIN rewards r ON ur.reward_id = r.id
            WHERE ur.user_id = ?
            ORDER BY ur.redeemed_at DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $userRewards = $stmt->fetchAll();
        
        echo ApiUtils::success($userRewards, 'Recompensas do usuário obtidas com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao obter recompensas do usuário: ' . $e->getMessage());
    }
}

/**
 * Estatísticas de pontos (admin)
 */
function handleGetStats($db) {
    ApiUtils::validateMethod(['GET']);
    ApiUtils::requireAdmin();
    
    try {
        // Total de pontos em circulação
        $stmt = $db->query("SELECT SUM(points) as total FROM users WHERE status = 'active'");
        $totalPoints = $stmt->fetch()['total'] ?? 0;
        
        // Pontos concedidos hoje
        $stmt = $db->query("
            SELECT SUM(points) as total 
            FROM points_history 
            WHERE points > 0 AND DATE(created_at) = CURDATE()
        ");
        $pointsToday = $stmt->fetch()['total'] ?? 0;
        
        // Pontos gastos hoje
        $stmt = $db->query("
            SELECT SUM(ABS(points)) as total 
            FROM points_history 
            WHERE points < 0 AND DATE(created_at) = CURDATE()
        ");
        $pointsSpentToday = $stmt->fetch()['total'] ?? 0;
        
        // Usuários mais ativos (por pontos)
        $stmt = $db->query("
            SELECT u.name, u.email, u.points
            FROM users u
            WHERE u.status = 'active'
            ORDER BY u.points DESC
            LIMIT 10
        ");
        $topUsers = $stmt->fetchAll();
        
        // Recompensas mais resgatadas
        $stmt = $db->query("
            SELECT 
                r.name,
                COUNT(ur.id) as redemptions,
                SUM(ur.points_used) as total_points
            FROM user_rewards ur
            JOIN rewards r ON ur.reward_id = r.id
            GROUP BY r.id
            ORDER BY redemptions DESC
            LIMIT 10
        ");
        $topRewards = $stmt->fetchAll();
        
        // Atividade por tipo
        $stmt = $db->query("
            SELECT 
                type,
                COUNT(*) as transactions,
                SUM(CASE WHEN points > 0 THEN points ELSE 0 END) as points_earned,
                SUM(CASE WHEN points < 0 THEN ABS(points) ELSE 0 END) as points_spent
            FROM points_history
            GROUP BY type
            ORDER BY transactions DESC
        ");
        $activityByType = $stmt->fetchAll();
        
        // Atividade diária (últimos 30 dias)
        $stmt = $db->query("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as transactions,
                SUM(CASE WHEN points > 0 THEN points ELSE 0 END) as points_earned,
                SUM(CASE WHEN points < 0 THEN ABS(points) ELSE 0 END) as points_spent
            FROM points_history
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $dailyActivity = $stmt->fetchAll();
        
        $stats = [
            'total_points_in_circulation' => $totalPoints,
            'points_earned_today' => $pointsToday,
            'points_spent_today' => $pointsSpentToday,
            'top_users' => $topUsers,
            'top_rewards' => $topRewards,
            'activity_by_type' => $activityByType,
            'daily_activity' => $dailyActivity
        ];
        
        echo ApiUtils::success($stats, 'Estatísticas obtidas com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao obter estatísticas');
    }
}

/**
 * Conceder pontos
 */
function awardPoints($db, $userId, $points, $type, $description, $metadata = []) {
    try {
        $db->beginTransaction();
        
        // Atualizar pontos do usuário
        $stmt = $db->prepare("UPDATE users SET points = points + ? WHERE id = ?");
        $stmt->execute([$points, $userId]);
        
        // Registrar histórico
        $stmt = $db->prepare("
            INSERT INTO points_history (user_id, points, type, description, metadata, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $points, $type, $description, json_encode($metadata)]);
        
        $db->commit();
        return true;
        
    } catch (Exception $e) {
        $db->rollBack();
        return false;
    }
}

/**
 * Deduzir pontos
 */
function deductPoints($db, $userId, $points, $type, $description, $metadata = []) {
    try {
        $db->beginTransaction();
        
        // Atualizar pontos do usuário
        $stmt = $db->prepare("UPDATE users SET points = points - ? WHERE id = ?");
        $stmt->execute([$points, $userId]);
        
        // Registrar histórico
        $stmt = $db->prepare("
            INSERT INTO points_history (user_id, points, type, description, metadata, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, -$points, $type, $description, json_encode($metadata)]);
        
        $db->commit();
        return true;
        
    } catch (Exception $e) {
        $db->rollBack();
        return false;
    }
}

/**
 * Criar recompensa (admin)
 */
function handleCreateReward($db, $input) {
    ApiUtils::validateMethod(['POST']);
    ApiUtils::requireAdmin();
    ApiUtils::validateRequired($input, ['name', 'points_required']);
    
    try {
        // Mapear tipos de recompensa
        $rewardTypeMap = [
            'produto' => 'gift',
            'desconto' => 'discount',
            'mensalidade grátis' => 'free_month'
        ];
        
        $rewardType = $rewardTypeMap[strtolower($input['reward_type'] ?? 'produto')] ?? 'gift';
        
        $stmt = $db->prepare("
            INSERT INTO rewards (name, description, points_required, reward_type, max_redemptions, is_active) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $input['name'],
            $input['description'] ?? '',
            $input['points_required'],
            $rewardType,
            $input['stock'] ?? null,
            ($input['status'] ?? 'active') === 'active' ? 1 : 0
        ]);
        
        $rewardId = $db->lastInsertId();
        
        // Log da ação
        ApiUtils::logActivity('reward_created', [
            'reward_id' => $rewardId,
            'name' => $input['name'],
            'points_required' => $input['points_required']
        ]);
        
        echo ApiUtils::success(['id' => $rewardId], 'Recompensa criada com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao criar recompensa: ' . $e->getMessage());
    }
}

/**
 * Obter recompensa específica (admin)
 */
function handleGetReward($db, $input) {
    ApiUtils::validateMethod(['GET']);
    ApiUtils::requireAdmin();
    ApiUtils::validateRequired($input, ['id']);
    
    try {
        $stmt = $db->prepare("SELECT * FROM rewards WHERE id = ?");
        $stmt->execute([$input['id']]);
        $reward = $stmt->fetch();
        
        if (!$reward) {
            echo ApiUtils::error('Recompensa não encontrada', 404);
            return;
        }
        
        // Mapear campos para o formato esperado pelo frontend
        $rewardTypeMap = [
            'gift' => 'produto',
            'discount' => 'desconto',
            'free_month' => 'mensalidade grátis'
        ];
        
        $reward['reward_type'] = $rewardTypeMap[$reward['reward_type']] ?? 'produto';
        $reward['status'] = $reward['is_active'] ? 'active' : 'inactive';
        $reward['stock'] = $reward['max_redemptions'];
        
        echo ApiUtils::success($reward, 'Recompensa obtida com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao obter recompensa');
    }
}

/**
 * Atualizar recompensa (admin)
 */
function handleUpdateReward($db, $input) {
    ApiUtils::validateMethod(['POST', 'PUT']);
    ApiUtils::requireAdmin();
    ApiUtils::validateRequired($input, ['id']);
    
    try {
        // Verificar se recompensa existe
        $stmt = $db->prepare("SELECT * FROM rewards WHERE id = ?");
        $stmt->execute([$input['id']]);
        $reward = $stmt->fetch();
        
        if (!$reward) {
            echo ApiUtils::error('Recompensa não encontrada', 404);
            return;
        }
        
        // Atualizar campos fornecidos
        $updateFields = [];
        $params = [];
        
        if (isset($input['name'])) {
            $updateFields[] = 'name = ?';
            $params[] = $input['name'];
        }
        
        if (isset($input['description'])) {
            $updateFields[] = 'description = ?';
            $params[] = $input['description'];
        }
        
        if (isset($input['points_required'])) {
            $updateFields[] = 'points_required = ?';
            $params[] = $input['points_required'];
        }
        
        if (isset($input['reward_type'])) {
            // Mapear tipos de recompensa
            $rewardTypeMap = [
                'produto' => 'gift',
                'desconto' => 'discount',
                'mensalidade grátis' => 'free_month'
            ];
            $rewardType = $rewardTypeMap[strtolower($input['reward_type'])] ?? 'gift';
            $updateFields[] = 'reward_type = ?';
            $params[] = $rewardType;
        }
        
        if (isset($input['stock'])) {
            $updateFields[] = 'max_redemptions = ?';
            $params[] = $input['stock'];
        }
        
        if (isset($input['status'])) {
            $updateFields[] = 'is_active = ?';
            $params[] = $input['status'] === 'active' ? 1 : 0;
        }
        
        if (empty($updateFields)) {
            echo ApiUtils::error('Nenhum campo para atualizar', 400);
            return;
        }
        
        $updateFields[] = 'updated_at = NOW()';
        $params[] = $input['id'];
        
        $query = "UPDATE rewards SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        // Log da ação
        ApiUtils::logActivity('reward_updated', [
            'reward_id' => $input['id'],
            'updated_fields' => array_keys($input)
        ]);
        
        echo ApiUtils::success(null, 'Recompensa atualizada com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao atualizar recompensa');
    }
}

/**
 * Deletar recompensa (admin)
 */
function handleDeleteReward($db, $input) {
    ApiUtils::validateMethod(['DELETE', 'POST']);
    ApiUtils::requireAdmin();
    ApiUtils::validateRequired($input, ['id']);
    
    try {
        // Verificar se recompensa existe
        $stmt = $db->prepare("SELECT * FROM rewards WHERE id = ?");
        $stmt->execute([$input['id']]);
        $reward = $stmt->fetch();
        
        if (!$reward) {
            echo ApiUtils::error('Recompensa não encontrada', 404);
            return;
        }
        
        // Verificar se há resgates pendentes
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM user_rewards WHERE reward_id = ? AND status = 'pending'");
        $stmt->execute([$input['id']]);
        $pendingRedemptions = $stmt->fetch()['count'];
        
        if ($pendingRedemptions > 0) {
            echo ApiUtils::error('Não é possível deletar recompensa com resgates pendentes', 400);
            return;
        }
        
        // Deletar recompensa
        $stmt = $db->prepare("DELETE FROM rewards WHERE id = ?");
        $stmt->execute([$input['id']]);
        
        // Log da ação
        ApiUtils::logActivity('reward_deleted', [
            'reward_id' => $input['id'],
            'reward_name' => $reward['name']
        ]);
        
        echo ApiUtils::success(null, 'Recompensa deletada com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao deletar recompensa');
    }
}

?>
