<?php
/**
 * Endpoint de Gerenciamento de Usuários
 * KMKZ IPTV API
 * 
 * Endpoints disponíveis:
 * GET /users.php?action=list - Listar usuários (admin)
 * GET /users.php?action=profile&id={id} - Perfil do usuário
 * POST /users.php?action=create - Criar usuário (admin)
 * PUT /users.php?action=update - Atualizar usuário
 * DELETE /users.php?action=delete&id={id} - Deletar usuário (admin)
 * GET /users.php?action=stats - Estatísticas de usuários (admin)
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
        handleListUsers($db, $input);
        break;
        
    case 'profile':
        handleGetProfile($db, $input);
        break;
        
    case 'create':
        handleCreateUser($db, $input);
        break;
        
    case 'update':
        handleUpdateUser($db, $input);
        break;
        
    case 'delete':
        handleDeleteUser($db, $input);
        break;
        
    case 'stats':
        handleUserStats($db);
        break;
        
    default:
        echo ApiUtils::error('Ação não encontrada', 404);
        break;
}

/**
 * Listar usuários (apenas admin)
 */
function handleListUsers($db, $input) {
    ApiUtils::validateMethod(['GET']);
    ApiUtils::requireAdmin();
    
    try {
        // Parâmetros de paginação
        $pagination = ApiUtils::getPagination(
            $input['page'] ?? 1,
            $input['per_page'] ?? DEFAULT_PAGE_SIZE
        );
        
        // Filtros
        $search = $input['search'] ?? '';
        $userType = $input['user_type'] ?? '';
        $status = $input['status'] ?? '';
        
        // Construir query
        $whereConditions = [];
        $params = [];
        
        if (!empty($search)) {
            $whereConditions[] = "(name LIKE ? OR email LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        
        if (!empty($userType)) {
            $whereConditions[] = "user_type = ?";
            $params[] = $userType;
        }
        
        if (!empty($status)) {
            $whereConditions[] = "status = ?";
            $params[] = $status;
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // Contar total
        $countQuery = "SELECT COUNT(*) as total FROM users {$whereClause}";
        $stmt = $db->prepare($countQuery);
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];
        
        // Buscar usuários
        $query = "SELECT id, name, email, user_type, status, points, last_login, login_count, created_at 
                  FROM users {$whereClause} 
                  ORDER BY created_at DESC 
                  LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $users = $stmt->fetchAll();
        
        // Calcular paginação
        $paginationInfo = ApiUtils::calculatePagination($total, $pagination['page'], $pagination['per_page']);
        
        echo ApiUtils::success($users, 'Usuários listados com sucesso', $paginationInfo);
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao listar usuários');
    }
}

/**
 * Obter perfil do usuário
 */
function handleGetProfile($db, $input) {
    ApiUtils::validateMethod(['GET']);
    ApiUtils::requireAuth();
    
    $userId = $input['id'] ?? $_SESSION['user_id'];
    
    // Verificar permissão (usuário só pode ver próprio perfil, admin pode ver qualquer um)
    if ($_SESSION['user_type'] !== 'admin' && $userId != $_SESSION['user_id']) {
        echo ApiUtils::error('Acesso negado', 403);
        return;
    }
    
    try {
        // Buscar usuário
        $stmt = $db->prepare("SELECT id, name, email, phone, user_type, status, points, last_login, login_count, created_at FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo ApiUtils::error('Usuário não encontrado', 404);
            return;
        }
        
        // Buscar assinatura ativa
        $stmt = $db->prepare("
            SELECT s.*, p.name as plan_name, p.price, p.duration_months 
            FROM subscriptions s 
            JOIN plans p ON s.plan_id = p.id 
            WHERE s.user_id = ? AND s.status = 'active' 
            ORDER BY s.end_date DESC LIMIT 1
        ");
        $stmt->execute([$userId]);
        $subscription = $stmt->fetch();
        
        // Buscar histórico de pontos recente
        $stmt = $db->prepare("
            SELECT action_type, points, description, created_at 
            FROM points_history 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$userId]);
        $pointsHistory = $stmt->fetchAll();
        
        // Montar resposta
        $profile = [
            'user' => $user,
            'subscription' => $subscription,
            'points_history' => $pointsHistory
        ];
        
        echo ApiUtils::success($profile, 'Perfil obtido com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao obter perfil');
    }
}

/**
 * Criar usuário (apenas admin)
 */
function handleCreateUser($db, $input) {
    ApiUtils::validateMethod(['POST']);
    ApiUtils::requireAdmin();
    ApiUtils::validateRequired($input, ['name', 'email', 'password']);
    
    // Sanitizar dados
    $data = ApiUtils::sanitize($input);
    
    // Validar dados
    $errors = DataValidator::validateUser($data);
    if (!empty($errors)) {
        echo ApiUtils::error('Dados inválidos: ' . implode(', ', $errors), 400);
        return;
    }
    
    try {
        // Verificar se email já existe
        if (emailExists($db, $data['email'])) {
            echo ApiUtils::error('Email já está em uso', 409);
            return;
        }
        
        // Criar usuário
        $hashedPassword = ApiUtils::hashPassword($data['password']);
        $userType = $data['user_type'] ?? 'user';
        $status = $data['status'] ?? 'active';
        
        $stmt = $db->prepare("
            INSERT INTO users (name, email, password, phone, user_type, status) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['name'],
            $data['email'],
            $hashedPassword,
            $data['phone'] ?? null,
            $userType,
            $status
        ]);
        
        $userId = $db->lastInsertId();
        
        // Log da ação
        ApiUtils::logActivity('user_created', [
            'created_user_id' => $userId,
            'created_by' => $_SESSION['user_id']
        ]);
        
        echo ApiUtils::success(['user_id' => $userId], 'Usuário criado com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao criar usuário');
    }
}

/**
 * Atualizar usuário
 */
function handleUpdateUser($db, $input) {
    ApiUtils::validateMethod(['PUT', 'POST']);
    ApiUtils::requireAuth();
    
    $userId = $input['id'] ?? $_SESSION['user_id'];
    
    // Verificar permissão
    if ($_SESSION['user_type'] !== 'admin' && $userId != $_SESSION['user_id']) {
        echo ApiUtils::error('Acesso negado', 403);
        return;
    }
    
    try {
        // Verificar se usuário existe
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo ApiUtils::error('Usuário não encontrado', 404);
            return;
        }
        
        // Preparar dados para atualização
        $updateFields = [];
        $params = [];
        
        if (isset($input['name']) && !empty($input['name'])) {
            $updateFields[] = "name = ?";
            $params[] = ApiUtils::sanitize($input['name']);
        }
        
        if (isset($input['phone'])) {
            $updateFields[] = "phone = ?";
            $params[] = ApiUtils::sanitize($input['phone']);
        }
        
        if (isset($input['password']) && !empty($input['password'])) {
            if (!ApiUtils::validatePassword($input['password'])) {
                echo ApiUtils::error('Senha deve ter pelo menos 6 caracteres', 400);
                return;
            }
            $updateFields[] = "password = ?";
            $params[] = ApiUtils::hashPassword($input['password']);
        }
        
        // Apenas admin pode alterar tipo e status
        if ($_SESSION['user_type'] === 'admin') {
            if (isset($input['user_type'])) {
                $updateFields[] = "user_type = ?";
                $params[] = $input['user_type'];
            }
            
            if (isset($input['status'])) {
                $updateFields[] = "status = ?";
                $params[] = $input['status'];
            }
        }
        
        if (empty($updateFields)) {
            echo ApiUtils::error('Nenhum campo para atualizar', 400);
            return;
        }
        
        // Atualizar usuário
        $params[] = $userId;
        $query = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        // Log da ação
        ApiUtils::logActivity('user_updated', [
            'updated_user_id' => $userId,
            'updated_by' => $_SESSION['user_id'],
            'fields' => array_keys($input)
        ]);
        
        echo ApiUtils::success(null, 'Usuário atualizado com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao atualizar usuário');
    }
}

/**
 * Deletar usuário (apenas admin)
 */
function handleDeleteUser($db, $input) {
    ApiUtils::validateMethod(['DELETE', 'POST']);
    ApiUtils::requireAdmin();
    
    $userId = $input['id'] ?? null;
    
    if (!$userId) {
        echo ApiUtils::error('ID do usuário é obrigatório', 400);
        return;
    }
    
    // Não permitir deletar próprio usuário
    if ($userId == $_SESSION['user_id']) {
        echo ApiUtils::error('Não é possível deletar seu próprio usuário', 400);
        return;
    }
    
    try {
        // Verificar se usuário existe
        $stmt = $db->prepare("SELECT name, email FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo ApiUtils::error('Usuário não encontrado', 404);
            return;
        }
        
        // Deletar usuário (cascade irá deletar dados relacionados)
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        // Log da ação
        ApiUtils::logActivity('user_deleted', [
            'deleted_user_id' => $userId,
            'deleted_user_name' => $user['name'],
            'deleted_user_email' => $user['email'],
            'deleted_by' => $_SESSION['user_id']
        ]);
        
        echo ApiUtils::success(null, 'Usuário deletado com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao deletar usuário');
    }
}

/**
 * Estatísticas de usuários (apenas admin)
 */
function handleUserStats($db) {
    ApiUtils::validateMethod(['GET']);
    ApiUtils::requireAdmin();
    
    try {
        // Total de usuários
        $stmt = $db->query("SELECT COUNT(*) as total FROM users");
        $totalUsers = $stmt->fetch()['total'];
        
        // Usuários ativos
        $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
        $activeUsers = $stmt->fetch()['total'];
        
        // Usuários por tipo
        $stmt = $db->query("SELECT user_type, COUNT(*) as count FROM users GROUP BY user_type");
        $usersByType = $stmt->fetchAll();
        
        // Novos usuários nos últimos 30 dias
        $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $newUsers30Days = $stmt->fetch()['total'];
        
        // Usuários com login nos últimos 7 dias
        $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $activeUsers7Days = $stmt->fetch()['total'];
        
        // Top usuários por pontos
        $stmt = $db->query("SELECT name, email, points FROM users ORDER BY points DESC LIMIT 10");
        $topUsersByPoints = $stmt->fetchAll();
        
        // Crescimento mensal
        $stmt = $db->query("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as count
            FROM users 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month
        ");
        $monthlyGrowth = $stmt->fetchAll();
        
        $stats = [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'users_by_type' => $usersByType,
            'new_users_30_days' => $newUsers30Days,
            'active_users_7_days' => $activeUsers7Days,
            'top_users_by_points' => $topUsersByPoints,
            'monthly_growth' => $monthlyGrowth
        ];
        
        echo ApiUtils::success($stats, 'Estatísticas obtidas com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao obter estatísticas');
    }
}

?>