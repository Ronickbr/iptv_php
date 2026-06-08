<?php
/**
 * Endpoint de Gerenciamento de Planos
 * KMKZ IPTV API
 * 
 * Endpoints disponíveis:
 * GET /plans.php?action=list - Listar planos
 * GET /plans.php?action=get&id={id} - Obter plano específico
 * POST /plans.php?action=create - Criar plano (admin)
 * PUT /plans.php?action=update - Atualizar plano (admin)
 * DELETE /plans.php?action=delete&id={id} - Deletar plano (admin)
 * GET /plans.php?action=stats - Estatísticas de planos (admin)
 * POST /plans.php?action=duplicate&id={id} - Duplicar plano (admin)
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
        handleListPlans($db, $input);
        break;
        
    case 'get':
        handleGetPlan($db, $input);
        break;
        
    case 'create':
        handleCreatePlan($db, $input);
        break;
        
    case 'update':
        handleUpdatePlan($db, $input);
        break;
        
    case 'delete':
        handleDeletePlan($db, $input);
        break;
        
    case 'stats':
        handlePlanStats($db);
        break;
        
    case 'duplicate':
        handleDuplicatePlan($db, $input);
        break;
        
    default:
        echo ApiUtils::error('Ação não encontrada', 404);
        break;
}

/**
 * Listar planos
 */
function handleListPlans($db, $input) {
    ApiUtils::validateMethod(['GET']);
    
    try {
        ensureMarketingPlans($db);

        // Parâmetros de filtro
        $status = $input['status'] ?? 'active';
        $popular = $input['popular'] ?? null;
        $includeInactive = isset($input['include_inactive']) && $_SESSION['user_type'] === 'admin';
        
        // Construir query
        $whereConditions = [];
        $params = [];
        
        if (!$includeInactive) {
            $whereConditions[] = "status = ?";
            $params[] = 'active';
        } elseif ($status) {
            $whereConditions[] = "status = ?";
            $params[] = $status;
        }
        
        if ($popular !== null) {
            $whereConditions[] = "is_popular = ?";
            $params[] = $popular ? 1 : 0;
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // Buscar planos
        $query = "SELECT * FROM plans {$whereClause} ORDER BY sort_order ASC, duration_months ASC";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $plans = $stmt->fetchAll();
        
        // Para cada plano, buscar estatísticas de assinaturas
        foreach ($plans as &$plan) {
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM subscriptions WHERE plan_id = ?");
            $stmt->execute([$plan['id']]);
            $plan['total_subscriptions'] = $stmt->fetch()['total'];
            
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM subscriptions WHERE plan_id = ? AND status = 'active'");
            $stmt->execute([$plan['id']]);
            $plan['active_subscriptions'] = $stmt->fetch()['total'];
            
            // Converter features de JSON para array se necessário
            if (!empty($plan['features'])) {
                $features = json_decode($plan['features'], true);
                $plan['features'] = $features ?: explode(',', $plan['features']);
            } else {
                $plan['features'] = [];
            }
        }
        
        echo ApiUtils::success($plans, 'Planos listados com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao listar planos');
    }
}

/**
 * Obter plano específico
 */
function handleGetPlan($db, $input) {
    ApiUtils::validateMethod(['GET']);
    
    $planId = $input['id'] ?? null;
    
    if (!$planId) {
        echo ApiUtils::error('ID do plano é obrigatório', 400);
        return;
    }
    
    try {
        // Buscar plano
        $stmt = $db->prepare("SELECT * FROM plans WHERE id = ?");
        $stmt->execute([$planId]);
        $plan = $stmt->fetch();
        
        if (!$plan) {
            echo ApiUtils::error('Plano não encontrado', 404);
            return;
        }
        
        // Verificar se usuário pode ver planos inativos
        if ($plan['status'] !== 'active' && (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin')) {
            echo ApiUtils::error('Plano não encontrado', 404);
            return;
        }
        
        // Buscar estatísticas
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM subscriptions WHERE plan_id = ?");
        $stmt->execute([$planId]);
        $plan['total_subscriptions'] = $stmt->fetch()['total'];
        
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM subscriptions WHERE plan_id = ? AND status = 'active'");
        $stmt->execute([$planId]);
        $plan['active_subscriptions'] = $stmt->fetch()['total'];
        
        // Converter features
        if (!empty($plan['features'])) {
            $features = json_decode($plan['features'], true);
            $plan['features'] = $features ?: explode(',', $plan['features']);
        } else {
            $plan['features'] = [];
        }
        
        echo ApiUtils::success($plan, 'Plano obtido com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao obter plano');
    }
}

/**
 * Criar plano (apenas admin)
 */
function handleCreatePlan($db, $input) {
    ApiUtils::validateMethod(['POST']);
    ApiUtils::requireAdmin();
    ApiUtils::validateRequired($input, ['name', 'price', 'duration_months']);
    
    // Sanitizar dados
    $data = ApiUtils::sanitize($input);
    
    // Validar dados
    $errors = DataValidator::validatePlan($data);
    if (!empty($errors)) {
        echo ApiUtils::error('Dados inválidos: ' . implode(', ', $errors), 400);
        return;
    }
    
    try {
        // Preparar features
        $features = null;
        if (isset($data['features'])) {
            if (is_array($data['features'])) {
                $features = json_encode($data['features']);
            } else {
                $features = $data['features'];
            }
        }
        
        // Criar plano
        $stmt = $db->prepare("
            INSERT INTO plans (
                name, description, price, duration_months, features, 
                max_devices, quality, is_popular, status, sort_order
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['price'],
            $data['duration_months'],
            $features,
            $data['max_devices'] ?? 1,
            $data['quality'] ?? 'HD',
            isset($data['is_popular']) ? ($data['is_popular'] ? 1 : 0) : 0,
            $data['status'] ?? 'active',
            $data['sort_order'] ?? 0
        ]);
        
        $planId = $db->lastInsertId();
        
        // Log da ação
        ApiUtils::logActivity('plan_created', [
            'plan_id' => $planId,
            'plan_name' => $data['name'],
            'created_by' => $_SESSION['user_id']
        ]);
        
        echo ApiUtils::success(['plan_id' => $planId], 'Plano criado com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao criar plano');
    }
}

/**
 * Atualizar plano (apenas admin)
 */
function handleUpdatePlan($db, $input) {
    ApiUtils::validateMethod(['PUT', 'POST']);
    ApiUtils::requireAdmin();
    
    $planId = $input['id'] ?? null;
    
    if (!$planId) {
        echo ApiUtils::error('ID do plano é obrigatório', 400);
        return;
    }
    
    try {
        // Verificar se plano existe
        $stmt = $db->prepare("SELECT * FROM plans WHERE id = ?");
        $stmt->execute([$planId]);
        $plan = $stmt->fetch();
        
        if (!$plan) {
            echo ApiUtils::error('Plano não encontrado', 404);
            return;
        }
        
        // Preparar dados para atualização
        $updateFields = [];
        $params = [];
        
        $allowedFields = [
            'name', 'description', 'price', 'duration_months', 
            'max_devices', 'quality', 'is_popular', 'status', 'sort_order'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                if ($field === 'is_popular') {
                    $updateFields[] = "{$field} = ?";
                    $params[] = $input[$field] ? 1 : 0;
                } else {
                    $updateFields[] = "{$field} = ?";
                    $params[] = ApiUtils::sanitize($input[$field]);
                }
            }
        }
        
        // Tratar features separadamente
        if (isset($input['features'])) {
            $updateFields[] = "features = ?";
            if (is_array($input['features'])) {
                $params[] = json_encode($input['features']);
            } else {
                $params[] = $input['features'];
            }
        }
        
        if (empty($updateFields)) {
            echo ApiUtils::error('Nenhum campo para atualizar', 400);
            return;
        }
        
        // Validar dados se preço ou duração foram alterados
        if (isset($input['price']) || isset($input['duration_months'])) {
            $testData = array_merge($plan, $input);
            $errors = DataValidator::validatePlan($testData);
            if (!empty($errors)) {
                echo ApiUtils::error('Dados inválidos: ' . implode(', ', $errors), 400);
                return;
            }
        }
        
        // Atualizar plano
        $params[] = $planId;
        $query = "UPDATE plans SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        // Log da ação
        ApiUtils::logActivity('plan_updated', [
            'plan_id' => $planId,
            'plan_name' => $plan['name'],
            'updated_fields' => array_keys($input),
            'updated_by' => $_SESSION['user_id']
        ]);
        
        echo ApiUtils::success(null, 'Plano atualizado com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao atualizar plano');
    }
}

/**
 * Deletar plano (apenas admin)
 */
function handleDeletePlan($db, $input) {
    ApiUtils::validateMethod(['DELETE', 'POST']);
    ApiUtils::requireAdmin();
    
    $planId = $input['id'] ?? null;
    
    if (!$planId) {
        echo ApiUtils::error('ID do plano é obrigatório', 400);
        return;
    }
    
    try {
        // Verificar se plano existe
        $stmt = $db->prepare("SELECT name FROM plans WHERE id = ?");
        $stmt->execute([$planId]);
        $plan = $stmt->fetch();
        
        if (!$plan) {
            echo ApiUtils::error('Plano não encontrado', 404);
            return;
        }
        
        // Verificar se há assinaturas ativas
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM subscriptions WHERE plan_id = ? AND status = 'active'");
        $stmt->execute([$planId]);
        $activeSubscriptions = $stmt->fetch()['total'];
        
        if ($activeSubscriptions > 0) {
            echo ApiUtils::error('Não é possível deletar plano com assinaturas ativas', 400);
            return;
        }
        
        // Deletar plano
        $stmt = $db->prepare("DELETE FROM plans WHERE id = ?");
        $stmt->execute([$planId]);
        
        // Log da ação
        ApiUtils::logActivity('plan_deleted', [
            'plan_id' => $planId,
            'plan_name' => $plan['name'],
            'deleted_by' => $_SESSION['user_id']
        ]);
        
        echo ApiUtils::success(null, 'Plano deletado com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao deletar plano');
    }
}

/**
 * Estatísticas de planos (apenas admin)
 */
function handlePlanStats($db) {
    ApiUtils::validateMethod(['GET']);
    ApiUtils::requireAdmin();
    
    try {
        // Total de planos
        $stmt = $db->query("SELECT COUNT(*) as total FROM plans");
        $totalPlans = $stmt->fetch()['total'];
        
        // Planos ativos
        $stmt = $db->query("SELECT COUNT(*) as total FROM plans WHERE status = 'active'");
        $activePlans = $stmt->fetch()['total'];
        
        // Planos populares
        $stmt = $db->query("SELECT COUNT(*) as total FROM plans WHERE is_popular = 1");
        $popularPlans = $stmt->fetch()['total'];
        
        // Planos por duração
        $stmt = $db->query("
            SELECT duration_months, COUNT(*) as count 
            FROM plans 
            WHERE status = 'active' 
            GROUP BY duration_months 
            ORDER BY duration_months
        ");
        $plansByDuration = $stmt->fetchAll();
        
        // Receita por plano
        $stmt = $db->query("
            SELECT 
                p.name,
                p.price,
                COUNT(s.id) as subscriptions,
                SUM(p.price) as total_revenue
            FROM plans p
            LEFT JOIN subscriptions s ON p.id = s.plan_id AND s.status = 'active'
            WHERE p.status = 'active'
            GROUP BY p.id
            ORDER BY total_revenue DESC
        ");
        $revenueByPlan = $stmt->fetchAll();
        
        // Planos mais populares (por assinaturas)
        $stmt = $db->query("
            SELECT 
                p.name,
                p.price,
                COUNT(s.id) as subscriptions
            FROM plans p
            LEFT JOIN subscriptions s ON p.id = s.plan_id
            WHERE p.status = 'active'
            GROUP BY p.id
            ORDER BY subscriptions DESC
            LIMIT 5
        ");
        $mostPopularPlans = $stmt->fetchAll();
        
        // Crescimento de assinaturas por mês
        $stmt = $db->query("
            SELECT 
                DATE_FORMAT(s.created_at, '%Y-%m') as month,
                p.name as plan_name,
                COUNT(s.id) as subscriptions
            FROM subscriptions s
            JOIN plans p ON s.plan_id = p.id
            WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(s.created_at, '%Y-%m'), p.id
            ORDER BY month, plan_name
        ");
        $monthlySubscriptions = $stmt->fetchAll();
        
        $stats = [
            'total_plans' => $totalPlans,
            'active_plans' => $activePlans,
            'popular_plans' => $popularPlans,
            'plans_by_duration' => $plansByDuration,
            'revenue_by_plan' => $revenueByPlan,
            'most_popular_plans' => $mostPopularPlans,
            'monthly_subscriptions' => $monthlySubscriptions
        ];
        
        echo ApiUtils::success($stats, 'Estatísticas obtidas com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao obter estatísticas');
    }
}

/**
 * Duplicar plano (apenas admin)
 */
function handleDuplicatePlan($db, $input) {
    ApiUtils::validateMethod(['POST']);
    ApiUtils::requireAdmin();
    
    $planId = $input['id'] ?? null;
    
    if (!$planId) {
        echo ApiUtils::error('ID do plano é obrigatório', 400);
        return;
    }
    
    try {
        // Buscar plano original
        $stmt = $db->prepare("SELECT * FROM plans WHERE id = ?");
        $stmt->execute([$planId]);
        $originalPlan = $stmt->fetch();
        
        if (!$originalPlan) {
            echo ApiUtils::error('Plano não encontrado', 404);
            return;
        }
        
        // Criar nome para o plano duplicado
        $newName = ($input['name'] ?? $originalPlan['name']) . ' (Cópia)';
        
        // Duplicar plano
        $stmt = $db->prepare("
            INSERT INTO plans (
                name, description, price, duration_months, features, 
                max_devices, quality, is_popular, status, sort_order
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $newName,
            $originalPlan['description'],
            $originalPlan['price'],
            $originalPlan['duration_months'],
            $originalPlan['features'],
            $originalPlan['max_devices'],
            $originalPlan['quality'],
            0, // Não marcar como popular por padrão
            'inactive', // Criar como inativo por padrão
            $originalPlan['sort_order'] + 1
        ]);
        
        $newPlanId = $db->lastInsertId();
        
        // Log da ação
        ApiUtils::logActivity('plan_duplicated', [
            'original_plan_id' => $planId,
            'new_plan_id' => $newPlanId,
            'new_plan_name' => $newName,
            'created_by' => $_SESSION['user_id']
        ]);
        
        echo ApiUtils::success([
            'plan_id' => $newPlanId,
            'name' => $newName
        ], 'Plano duplicado com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao duplicar plano');
    }
}

?>
