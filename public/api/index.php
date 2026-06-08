<?php
/**
 * API Principal - KMKZ IPTV
 * Roteador central para todos os endpoints da API
 * 
 * Este arquivo serve como ponto de entrada único para a API,
 * roteando as requisições para os endpoints apropriados.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/middleware.php';

// Aplicar middleware de segurança
ApiMiddleware::apply();

// Obter informações da requisição
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];
$pathInfo = parse_url($requestUri, PHP_URL_PATH);

// Remover prefixo da API se existir
$apiPrefix = '/api';
if (strpos($pathInfo, $apiPrefix) === 0) {
    $pathInfo = substr($pathInfo, strlen($apiPrefix));
}

// Remover barras extras
$pathInfo = trim($pathInfo, '/');

// Definir rotas disponíveis
$routes = [
    // Autenticação
    'auth' => __DIR__ . '/auth.php',
    'auth.php' => __DIR__ . '/auth.php',
    
    // Usuários
    'users' => __DIR__ . '/users.php',
    'users.php' => __DIR__ . '/users.php',
    
    // Planos
    'plans' => __DIR__ . '/plans.php',
    'plans.php' => __DIR__ . '/plans.php',
    
    // Assinaturas
    'subscriptions' => __DIR__ . '/subscriptions.php',
    'subscriptions.php' => __DIR__ . '/subscriptions.php',
    
    // Pagamentos
    'payments' => __DIR__ . '/payments.php',
    'payments.php' => __DIR__ . '/payments.php',
    
    // Pontos e Recompensas
    'points' => __DIR__ . '/points.php',
    'points.php' => __DIR__ . '/points.php',
    
    // Dashboard
    'dashboard' => __DIR__ . '/dashboard.php',
    'dashboard.php' => __DIR__ . '/dashboard.php',
];

// Rota raiz - informações da API
if (empty($pathInfo) || $pathInfo === 'index.php') {
    handleApiInfo();
    exit();
}

// Verificar se a rota existe
if (!isset($routes[$pathInfo])) {
    echo ApiUtils::error('Endpoint não encontrado', 404);
    exit();
}

// Verificar se o arquivo existe
$targetFile = $routes[$pathInfo];
if (!file_exists($targetFile)) {
    echo ApiUtils::error('Endpoint não implementado', 501);
    exit();
}

// Incluir o arquivo do endpoint
require_once $targetFile;

/**
 * Informações da API
 */
function handleApiInfo() {
    ApiUtils::validateMethod(['GET']);
    
    $info = [
        'name' => API_NAME,
        'version' => API_VERSION,
        'description' => 'API REST para gerenciamento do sistema KMKZ IPTV',
        'base_url' => API_BASE_URL,
        'documentation' => API_BASE_URL . '/docs',
        'status' => 'online',
        'timestamp' => date('Y-m-d H:i:s'),
        'timezone' => date_default_timezone_get(),
        'endpoints' => [
            'auth' => [
                'description' => 'Autenticação e gerenciamento de sessões',
                'methods' => ['POST'],
                'actions' => ['login', 'logout', 'register', 'check', 'forgot_password']
            ],
            'users' => [
                'description' => 'Gerenciamento de usuários',
                'methods' => ['GET', 'POST', 'PUT', 'DELETE'],
                'actions' => ['list', 'get', 'create', 'update', 'delete', 'profile', 'stats']
            ],
            'plans' => [
                'description' => 'Gerenciamento de planos',
                'methods' => ['GET', 'POST', 'PUT', 'DELETE'],
                'actions' => ['list', 'get', 'create', 'update', 'delete', 'stats', 'duplicate']
            ],
            'subscriptions' => [
                'description' => 'Gerenciamento de assinaturas',
                'methods' => ['GET', 'POST', 'PUT'],
                'actions' => ['list', 'get', 'create', 'update', 'cancel', 'renew', 'user_subscriptions', 'stats']
            ],
            'payments' => [
                'description' => 'Gerenciamento de pagamentos',
                'methods' => ['GET', 'POST', 'PUT'],
                'actions' => ['list', 'get', 'create', 'update_status', 'process_webhook', 'user_payments', 'stats', 'generate_pix']
            ],
            'points' => [
                'description' => 'Sistema de pontos e recompensas',
                'methods' => ['GET', 'POST'],
                'actions' => ['balance', 'history', 'rules', 'award', 'deduct', 'leaderboard', 'rewards', 'redeem', 'user_rewards', 'stats']
            ],
            'dashboard' => [
                'description' => 'Dashboards e estatísticas',
                'methods' => ['GET', 'POST'],
                'actions' => ['admin', 'user', 'stats', 'recent_activity', 'notifications', 'mark_notification_read']
            ]
        ],
        'authentication' => [
            'type' => 'session',
            'required' => true,
            'admin_required' => 'Para algumas operações'
        ],
        'rate_limiting' => [
            'enabled' => true,
            'requests_per_minute' => RATE_LIMIT_REQUESTS,
            'window_minutes' => RATE_LIMIT_WINDOW
        ],
        'response_format' => [
            'success' => [
                'success' => true,
                'data' => 'mixed',
                'message' => 'string',
                'pagination' => 'object (when applicable)'
            ],
            'error' => [
                'success' => false,
                'error' => 'string',
                'code' => 'integer'
            ]
        ],
        'supported_methods' => ['GET', 'POST', 'PUT', 'DELETE'],
        'content_types' => ['application/json', 'application/x-www-form-urlencoded'],
        'features' => [
            'User Management' => 'Gerenciamento completo de usuários',
            'Plan Management' => 'Criação e gerenciamento de planos de assinatura',
            'Subscription Management' => 'Controle de assinaturas e renovações',
            'Payment Processing' => 'Processamento de pagamentos com múltiplos métodos',
            'Points System' => 'Sistema gamificado de pontos e recompensas',
            'Dashboard Analytics' => 'Dashboards com estatísticas e métricas',
            'Security' => 'Rate limiting, validação de entrada, proteção CSRF',
            'Logging' => 'Log de atividades e auditoria'
        ]
    ];
    
    echo ApiUtils::success($info, 'Informações da API obtidas com sucesso');
}

?>