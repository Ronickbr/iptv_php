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
$response = ['success' => false, 'message' => ''];

// Processar ação
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'create':
        $name = $_POST['name'] ?? '';
        $duration = (int)($_POST['duration'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        $discount = (float)($_POST['discount'] ?? 0);
        $description = $_POST['description'] ?? '';
        $active = isset($_POST['active']) && $_POST['active'] === 'true';
        
        if (empty($name) || $duration <= 0 || $price <= 0) {
            $response['message'] = 'Dados inválidos';
            break;
        }
        
        $planId = createPlan($db, $name, $duration, $price, $discount, $description, $active);
        if ($planId) {
            $response['success'] = true;
            $response['message'] = 'Plano criado com sucesso!';
            $response['plan_id'] = $planId;
        } else {
            $response['message'] = 'Erro ao criar plano';
        }
        break;
        
    case 'update':
        $id = (int)($_POST['id'] ?? 0);
        $name = $_POST['name'] ?? '';
        $duration = (int)($_POST['duration'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        $discount = (float)($_POST['discount'] ?? 0);
        $description = $_POST['description'] ?? '';
        $active = isset($_POST['active']) && $_POST['active'] === 'true';
        
        if ($id <= 0 || empty($name) || $duration <= 0 || $price <= 0) {
            $response['message'] = 'Dados inválidos';
            break;
        }
        
        if (updatePlan($db, $id, $name, $duration, $price, $discount, $description, $active)) {
            $response['success'] = true;
            $response['message'] = 'Plano atualizado com sucesso!';
        } else {
            $response['message'] = 'Erro ao atualizar plano';
        }
        break;
        
    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        
        if ($id <= 0) {
            $response['message'] = 'ID inválido';
            break;
        }
        
        if (deletePlan($db, $id)) {
            $response['success'] = true;
            $response['message'] = 'Plano excluído com sucesso!';
        } else {
            $response['message'] = 'Erro ao excluir plano. Verifique se não há assinaturas ativas.';
        }
        break;
        
    case 'toggle_status':
        $id = (int)($_POST['id'] ?? 0);
        $active = isset($_POST['active']) && $_POST['active'] === 'true';
        
        if ($id <= 0) {
            $response['message'] = 'ID inválido';
            break;
        }
        
        if (togglePlanStatus($db, $id, $active)) {
            $response['success'] = true;
            $response['message'] = 'Status do plano atualizado!';
        } else {
            $response['message'] = 'Erro ao atualizar status do plano';
        }
        break;
        
    case 'get_plans':
        $plans = getPlans($db);
        $response['success'] = true;
        $response['plans'] = $plans;
        break;
        
    default:
        $response['message'] = 'Ação inválida';
        break;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
