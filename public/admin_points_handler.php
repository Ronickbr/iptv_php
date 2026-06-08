<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Verificar se é uma requisição AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    exit('Acesso negado');
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Acesso negado');
}

$sessionRole = $_SESSION['user_type'] ?? null;
if ($sessionRole === null && isset($_SESSION['user_data']) && is_array($_SESSION['user_data'])) {
    $sessionRole = $_SESSION['user_data']['role'] ?? ($_SESSION['user_data']['user_type'] ?? null);
}

if ($sessionRole !== 'admin') {
    http_response_code(403);
    exit('Acesso negado');
}

$db = getDB();
$action = $_POST['action'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($action) {
    case 'get_rules':
        $rules = getPointsRules($db);
        echo json_encode(['success' => true, 'rules' => $rules], JSON_UNESCAPED_UNICODE);
        break;
        
    case 'get_rule':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido'], JSON_UNESCAPED_UNICODE);
            break;
        }
        
        $rule = getPointsRule($db, $id);
        if ($rule) {
            echo json_encode(['success' => true, 'rule' => $rule], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['success' => false, 'message' => 'Regra não encontrada'], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    case 'create_rule':
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'action_type' => $_POST['action_type'] ?? '',
            'points_awarded' => (int)($_POST['points_awarded'] ?? 0),
            'conditions_json' => $_POST['conditions_json'] ?? '{}',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'max_per_user' => !empty($_POST['max_per_user']) ? (int)$_POST['max_per_user'] : null,
            'max_per_day' => !empty($_POST['max_per_day']) ? (int)$_POST['max_per_day'] : null
        ];
        
        // Validação básica
        if (empty($data['name']) || empty($data['action_type']) || $data['points_awarded'] <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dados inválidos. Verifique os campos obrigatórios.'], JSON_UNESCAPED_UNICODE);
            break;
        }
        
        if (createPointsRule($db, $data)) {
            echo json_encode(['success' => true, 'message' => 'Regra criada com sucesso!'], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao criar regra'], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    case 'update_rule':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido'], JSON_UNESCAPED_UNICODE);
            break;
        }
        
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'action_type' => $_POST['action_type'] ?? '',
            'points_awarded' => (int)($_POST['points_awarded'] ?? 0),
            'conditions_json' => $_POST['conditions_json'] ?? '{}',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'max_per_user' => !empty($_POST['max_per_user']) ? (int)$_POST['max_per_user'] : null,
            'max_per_day' => !empty($_POST['max_per_day']) ? (int)$_POST['max_per_day'] : null
        ];
        
        // Validação básica
        if (empty($data['name']) || empty($data['action_type']) || $data['points_awarded'] <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dados inválidos. Verifique os campos obrigatórios.'], JSON_UNESCAPED_UNICODE);
            break;
        }
        
        if (updatePointsRule($db, $id, $data)) {
            echo json_encode(['success' => true, 'message' => 'Regra atualizada com sucesso!'], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar regra'], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    case 'delete_rule':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido'], JSON_UNESCAPED_UNICODE);
            break;
        }
        
        if (deletePointsRule($db, $id)) {
            echo json_encode(['success' => true, 'message' => 'Regra excluída com sucesso!'], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao excluir regra'], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    case 'toggle_status':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido'], JSON_UNESCAPED_UNICODE);
            break;
        }
        
        if (togglePointsRuleStatus($db, $id)) {
            echo json_encode(['success' => true, 'message' => 'Status alterado com sucesso!'], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao alterar status'], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    case 'award_points':
        $userId = (int)($_POST['user_id'] ?? 0);
        $actionType = $_POST['action_type'] ?? '';
        $referenceId = !empty($_POST['reference_id']) ? (int)$_POST['reference_id'] : null;
        $customData = json_decode($_POST['custom_data'] ?? '{}', true) ?: [];
        
        if ($userId <= 0 || empty($actionType)) {
            echo json_encode(['success' => false, 'message' => 'Dados inválidos'], JSON_UNESCAPED_UNICODE);
            break;
        }
        
        $pointsAwarded = awardPointsByRule($db, $userId, $actionType, $referenceId, $customData);
        
        if ($pointsAwarded > 0) {
            echo json_encode([
                'success' => true, 
                'message' => "Pontos concedidos com sucesso! Total: {$pointsAwarded} pontos",
                'points_awarded' => $pointsAwarded
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['success' => false, 'message' => 'Nenhum ponto foi concedido. Verifique as regras e limites.'], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Ação inválida'], JSON_UNESCAPED_UNICODE);
        break;
}
?>
