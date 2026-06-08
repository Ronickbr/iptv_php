<?php
/**
 * Endpoint de Gerenciamento de Configurações
 * KMKZ IPTV API
 * 
 * Endpoints disponíveis:
 * GET /settings.php?action=get - Obter configurações
 * POST /settings.php?action=update - Atualizar configurações (admin)
 * GET /settings.php?action=list - Listar todas as configurações (admin)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/middleware.php';

// Aplicar middleware de segurança
ApiMiddleware::apply();

// Validar método HTTP
ApiUtils::validateHttpMethod(['GET', 'POST']);

// Obter ação
$action = $_GET['action'] ?? $_POST['action'] ?? 'get';

// Roteamento
switch ($action) {
    case 'get':
        getSettings();
        break;
    case 'update':
        updateSettings();
        break;
    case 'list':
        listSettings();
        break;
    default:
        echo ApiUtils::error('Ação não encontrada', 404);
}

/**
 * Obter configurações públicas
 */
function getSettings() {
    try {
        $db = getDB();
        
        // Configurações públicas (não sensíveis)
        $publicSettings = [
            'site_name',
            'contact_email',
            'whatsapp',
            'auto_renewal',
            'points_enabled',
            'rewards_enabled'
        ];
        
        $placeholders = str_repeat('?,', count($publicSettings) - 1) . '?';
        $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($placeholders)");
        $stmt->execute($publicSettings);
        
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        echo ApiUtils::success($settings, 'Configurações obtidas com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao obter configurações');
    }
}

/**
 * Atualizar configurações (admin apenas)
 */
function updateSettings() {
    // Verificar se é admin
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
        echo ApiUtils::error('Acesso negado', 403);
        return;
    }
    
    try {
        $db = getDB();
        $input = ApiUtils::getInputData();
        
        // Validar dados obrigatórios
        $requiredFields = ['settings'];
        $validation = ApiUtils::validateRequiredFields($input, $requiredFields);
        if (!$validation['valid']) {
            echo ApiUtils::error('Dados inválidos: ' . implode(', ', $validation['missing']));
            return;
        }
        
        $settings = $input['settings'];
        
        // Configurações permitidas para atualização
        $allowedSettings = [
            'site_name',
            'contact_email',
            'whatsapp',
            'pix_key',
            'mercadopago_token',
            'auto_renewal',
            'points_enabled',
            'rewards_enabled'
        ];
        
        $db->beginTransaction();
        
        foreach ($settings as $key => $value) {
            if (!in_array($key, $allowedSettings)) {
                continue;
            }
            
            // Sanitizar valor
            $value = ApiUtils::sanitizeInput($value);
            
            // Atualizar ou inserir configuração
            $stmt = $db->prepare("
                INSERT INTO settings (setting_key, setting_value, updated_at) 
                VALUES (?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value), 
                updated_at = NOW()
            ");
            $stmt->execute([$key, $value]);
        }
        
        $db->commit();
        
        echo ApiUtils::success(null, 'Configurações atualizadas com sucesso');
        
    } catch (Exception $e) {
        if (isset($db)) {
            $db->rollBack();
        }
        echo ApiUtils::error('Erro ao atualizar configurações');
    }
}

/**
 * Listar todas as configurações (admin apenas)
 */
function listSettings() {
    // Verificar se é admin
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
        echo ApiUtils::error('Acesso negado', 403);
        return;
    }
    
    try {
        $db = getDB();
        
        $stmt = $db->query("
            SELECT setting_key, setting_value, created_at, updated_at 
            FROM settings 
            ORDER BY setting_key
        ");
        
        $settings = $stmt->fetchAll();
        
        echo ApiUtils::success($settings, 'Configurações listadas com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao listar configurações');
    }
}

?>