<?php
/**
 * Configurações e funções para integração com a API KMKZ IPTV
 */

require_once __DIR__ . '/../config/database.php';
if (function_exists('loadDotEnvIfPresent')) {
    loadDotEnvIfPresent();
}

// Configuração da API
if (!defined('API_BASE_URL')) {
    $envApiBaseUrl = getenv('API_BASE_URL');
    if ($envApiBaseUrl) {
        define('API_BASE_URL', rtrim($envApiBaseUrl, '/'));
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        define('API_BASE_URL', $scheme . '://' . $host);
    }
}
define('API_TIMEOUT', 10);
define('API_CONNECT_TIMEOUT', 5);
define('API_VERIFY_SSL', filter_var(getenv('API_VERIFY_SSL') ?: '1', FILTER_VALIDATE_BOOLEAN));

function apiJsonResponse($success, $message = '', $data = null, $httpCode = null) {
    if ($httpCode !== null) {
        http_response_code((int)$httpCode);
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => (bool)$success,
        'message' => (string)$message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Função para fazer requisições à API
 * 
 * @param string $endpoint Endpoint da API (ex: '/users', '/plans')
 * @param string $method Método HTTP (GET, POST, PUT, DELETE)
 * @param array|null $data Dados para enviar na requisição
 * @param array $headers Headers adicionais
 * @return array Resposta da API
 */
function makeApiRequest($endpoint, $method = 'GET', $data = null, $headers = []) {
    $ch = curl_init();
    
    // Headers padrão
    $defaultHeaders = [
        'Content-Type: application/json; charset=utf-8',
        'Accept: application/json'
    ];
    
    // Mesclar headers
    $allHeaders = array_merge($defaultHeaders, $headers);
    
    // Configurações básicas do cURL
    curl_setopt_array($ch, [
        CURLOPT_URL => API_BASE_URL . $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => API_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => API_CONNECT_TIMEOUT,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $allHeaders,
        CURLOPT_SSL_VERIFYPEER => API_VERIFY_SSL,
        CURLOPT_FOLLOWLOCATION => true
    ]);
    
    // Adicionar dados para métodos POST/PUT
    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
    }
    
    // Executar requisição
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Verificar erros de cURL
    if ($error) {
        return [
            'success' => false,
            'error' => 'Erro de conexão: ' . $error,
            'http_code' => 0
        ];
    }
    
    // Decodificar resposta JSON
    $decodedResponse = json_decode($response, true);
    
    // Se não conseguiu decodificar JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'error' => 'Resposta inválida da API',
            'http_code' => $httpCode,
            'raw_response' => $response
        ];
    }
    
    // Adicionar código HTTP à resposta
    $decodedResponse['http_code'] = $httpCode;
    
    return $decodedResponse;
}

/**
 * Função para fazer login via API
 * 
 * @param string $email
 * @param string $password
 * @return array
 */
function apiLogin($email, $password) {
    return makeApiRequest('/api/auth.php?action=login', 'POST', [
        'email' => $email,
        'password' => $password
    ]);
}

/**
 * Função para verificar se o usuário está logado
 * 
 * @return array
 */
function apiCheckAuth() {
    $response = makeApiRequest('/api/auth.php?action=check');
    if (is_array($response) && isset($response['data']) && is_array($response['data'])) {
        if (!array_key_exists('logged_in', $response['data']) && array_key_exists('authenticated', $response['data'])) {
            $response['data']['logged_in'] = (bool)$response['data']['authenticated'];
        }

        if (isset($response['data']['user']) && is_array($response['data']['user'])) {
            if (!array_key_exists('role', $response['data']['user']) && array_key_exists('user_type', $response['data']['user'])) {
                $response['data']['user']['role'] = $response['data']['user']['user_type'];
            }
        }
    }

    return $response;
}

/**
 * Função para fazer logout via API
 * 
 * @return array
 */
function apiLogout() {
    return makeApiRequest('/api/auth.php?action=logout', 'POST');
}

/**
 * Função para obter lista de planos
 * 
 * @return array
 */
function apiGetPlans() {
    return makeApiRequest('/api/plans.php?action=list');
}

/**
 * Função para obter um plano específico
 * 
 * @param int $planId
 * @return array
 */
function apiGetPlan($planId) {
    return makeApiRequest('/api/plans.php?action=get&id=' . $planId);
}

/**
 * Função para criar um usuário
 * 
 * @param array $userData
 * @return array
 */
function apiCreateUser($userData) {
    return makeApiRequest('/api/users.php?action=create', 'POST', $userData);
}

/**
 * Função para criar uma assinatura
 * 
 * @param array $subscriptionData
 * @return array
 */
function apiCreateSubscription($subscriptionData) {
    return makeApiRequest('/api/subscriptions.php?action=create', 'POST', $subscriptionData);
}

/**
 * Função para acessar dashboard do usuário
 */
function apiGetUserDashboard() {
    return makeApiRequest('/api/dashboard.php?action=user', 'GET');
}

/**
 * Função para obter dashboard do admin
 */
function apiGetAdminDashboard() {
    return makeApiRequest('/api/dashboard.php?action=admin', 'GET');
}

/**
 * Função para validar resposta da API
 * 
 * @param array $response
 * @return bool
 */
function isApiResponseValid($response) {
    return is_array($response) && 
           isset($response['success']) && 
           $response['http_code'] === 200;
}

/**
 * Função para obter dados da resposta da API
 * 
 * @param array $response
 * @return mixed
 */
function getApiResponseData($response) {
    if (isApiResponseValid($response) && $response['success']) {
        return $response['data'] ?? null;
    }
    return null;
}

/**
 * Função para obter erro da resposta da API
 * 
 * @param array $response
 * @return string
 */
function getApiResponseError($response) {
    if (is_array($response)) {
        return $response['error'] ?? 'Erro desconhecido';
    }
    return 'Erro de comunicação com a API';
}

/**
 * Função para sanitizar entrada de dados
 * 
 * @param string $input
 * @return string
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Função para validar email
 * 
 * @param string $email
 * @return bool
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Função para gerar dados de fallback quando a API não está disponível
 * 
 * @param string $type Tipo de dados (plans, testimonials, etc.)
 * @return array
 */
function getFallbackData($type) {
    switch ($type) {
        case 'plans':
            // Retornar dados estáticos diretamente para teste
            return [
                [
                    'id' => 1,
                    'name' => 'Mensal',
                    'description' => 'Plano ideal para testar nosso serviço',
                    'price' => 35.00,
                    'duration_months' => 1,
                    'is_popular' => false,
                    'quality' => 'FHD',
                    'max_devices' => 2,
                    'features' => ['Mais de 500 canais', 'Qualidade HD', 'Suporte 24/7', '2 dispositivos']
                ],
                [
                    'id' => 2,
                    'name' => 'Trimestral',
                    'description' => 'Economia garantida por 3 meses',
                    'price' => 99.00,
                    'duration_months' => 3,
                    'is_popular' => false,
                    'quality' => 'FHD',
                    'max_devices' => 2,
                    'features' => ['Mais de 500 canais', 'Qualidade HD', 'Suporte 24/7', '2 dispositivos']
                ],
                [
                    'id' => 3,
                    'name' => 'Semestral',
                    'description' => 'Nosso plano mais popular',
                    'price' => 180.00,
                    'duration_months' => 6,
                    'is_popular' => true,
                    'quality' => 'FHD',
                    'max_devices' => 2,
                    'features' => ['Mais de 500 canais', 'Qualidade 4K', 'Suporte 24/7', '2 dispositivos']
                ],
                [
                    'id' => 4,
                    'name' => 'Anual',
                    'description' => 'Máxima economia anual',
                    'price' => 300.00,
                    'duration_months' => 12,
                    'is_popular' => false,
                    'quality' => 'FHD',
                    'max_devices' => 2,
                    'features' => ['Mais de 500 canais', 'Qualidade 4K', 'Suporte 24/7', '2 dispositivos']
                ]
            ];
            
        case 'testimonials':
            return [
                [
                    'name' => 'João Silva',
                    'comment' => 'Excelente qualidade de imagem e som. Recomendo!'
                ],
                [
                    'name' => 'Maria Santos',
                    'comment' => 'Melhor custo-benefício do mercado. Muito satisfeita!'
                ],
                [
                    'name' => 'Pedro Costa',
                    'comment' => 'Suporte técnico nota 10. Sempre me ajudam rapidamente.'
                ]
            ];
            
        default:
            return [];
    }
}
?>
