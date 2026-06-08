<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/api.php';

// Verificar método da requisição
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $normalizeUser = function($user) {
        if (!is_array($user)) {
            return null;
        }

        $role = $user['role'] ?? ($user['user_type'] ?? null);
        if ($role === null) {
            $role = 'user';
        }

        return [
            'id' => $user['id'] ?? null,
            'name' => $user['name'] ?? null,
            'email' => $user['email'] ?? null,
            'role' => $role
        ];
    };

    if ($method === 'POST') {
        // Login
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['email']) || !isset($input['password'])) {
            apiJsonResponse(false, 'Email e senha são obrigatórios', null, 400);
        }
        
        $email = sanitizeInput($input['email']);
        $password = $input['password'];
        
        if (!isValidEmail($email)) {
            apiJsonResponse(false, 'Email inválido', null, 400);
        }
        
        // Fazer login via API
        $loginResponse = apiLogin($email, $password);
        
        if (isApiResponseValid($loginResponse) && $loginResponse['success']) {
            $apiUser = $loginResponse['data']['user'] ?? null;
            $normalizedUser = $normalizeUser($apiUser);

            if ($normalizedUser) {
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $_SESSION['user_logged_in'] = true;
                $_SESSION['user_data'] = $normalizedUser;
            }

            apiJsonResponse(true, 'Login realizado com sucesso', [
                'logged_in' => $normalizedUser ? true : false,
                'user' => $normalizedUser
            ]);
        } else {
            $errorMessage = getApiResponseError($loginResponse) ?: 'Credenciais inválidas';
            apiJsonResponse(false, $errorMessage, null, 401);
        }
        
    } elseif ($method === 'GET' && $action === 'check') {
        // Verificar status de autenticação
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] && isset($_SESSION['user_data'])) {
            $normalizedUser = $normalizeUser($_SESSION['user_data']);
            apiJsonResponse(true, 'Status verificado (sessão local)', [
                'logged_in' => true,
                'user' => $normalizedUser
            ]);
        }

        $authResponse = apiCheckAuth();
        if (isApiResponseValid($authResponse) && $authResponse['success']) {
            $apiData = $authResponse['data'] ?? [];
            $authenticated = (bool)($apiData['authenticated'] ?? false);
            $normalizedUser = $authenticated ? $normalizeUser($apiData['user'] ?? null) : null;

            if ($authenticated && $normalizedUser) {
                $_SESSION['user_logged_in'] = true;
                $_SESSION['user_data'] = $normalizedUser;
            }

            apiJsonResponse(true, 'Status verificado', [
                'logged_in' => $authenticated,
                'user' => $normalizedUser
            ]);
        }

        apiJsonResponse(false, 'Não autenticado', ['logged_in' => false], 401);
        
    } elseif ($method === 'POST' && $action === 'logout') {
        // Logout
        $logoutResponse = apiLogout();
        
        // Limpar sessão local independentemente da resposta da API
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_destroy();
        
        if (isApiResponseValid($logoutResponse)) {
            apiJsonResponse(true, 'Logout realizado com sucesso');
        } else {
            apiJsonResponse(true, 'Logout realizado com sucesso (sessão local)');
        }
        
    } else {
        apiJsonResponse(false, 'Ação não suportada', null, 400);
    }
    
} catch (Exception $e) {
    error_log('Erro em auth.php: ' . $e->getMessage());
    apiJsonResponse(false, 'Erro interno do servidor', null, 500);
}
?>
