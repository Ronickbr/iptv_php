<?php
/**
 * Configuração Principal da API KMKZ IPTV
 * 
 * Este arquivo contém todas as configurações e utilitários necessários
 * para o funcionamento da API REST.
 */

define('API_DEBUG', filter_var(getenv('API_DEBUG') ?: '0', FILTER_VALIDATE_BOOLEAN));

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

ini_set('display_errors', API_DEBUG ? '1' : '0');
ini_set('display_startup_errors', API_DEBUG ? '1' : '0');
error_reporting(API_DEBUG ? E_ALL : (E_ALL & ~E_DEPRECATED & ~E_STRICT));

// Configurações de sessão
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', $isHttps ? '1' : '0');

// Iniciar sessão se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Definir timezone
date_default_timezone_set('America/Sao_Paulo');

// Constantes da API
define('API_VERSION', '1.0');
define('API_NAME', 'KMKZ IPTV API');
if (!defined('API_BASE_URL')) {
    define('API_BASE_URL', '/api/');
}

// Configurações de paginação
define('DEFAULT_PAGE_SIZE', 20);
define('MAX_PAGE_SIZE', 100);

// Configurações de cache
define('CACHE_ENABLED', false);
define('CACHE_TTL', 3600);

// Configurações de rate limiting
define('RATE_LIMIT_ENABLED', true);
define('RATE_LIMIT_REQUESTS', 100);
define('RATE_LIMIT_WINDOW', 3600);

// Configurações de upload
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf']);

// Configurações de segurança
define('SESSION_TIMEOUT', 3600); // 1 hora
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutos

// Configurações de pontos
define('POINTS_DAILY_LOGIN', 10);
define('POINTS_REFERRAL', 100);
define('POINTS_SUBSCRIPTION', 50);

// Incluir dependências
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

/**
 * Classe utilitária para a API
 */
class ApiUtils {
    
    /**
     * Configurar cabeçalhos HTTP padrão
     */
    public static function setHeaders() {
        header('Content-Type: application/json; charset=utf-8');
        $appEnv = strtolower(getenv('APP_ENV') ?: 'production');
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $allowedOriginsRaw = (string)(getenv('CORS_ALLOWED_ORIGINS') ?: '');
        $allowedOrigins = array_values(array_filter(array_map('trim', explode(',', $allowedOriginsRaw))));

        if ($appEnv !== 'production' && empty($allowedOrigins)) {
            header('Access-Control-Allow-Origin: *');
        } else {
            $allowOriginHeader = null;

            if ($origin !== '' && $host !== '') {
                $originHost = parse_url($origin, PHP_URL_HOST);
                if ($originHost && $originHost === $host) {
                    $allowOriginHeader = $origin;
                } elseif (in_array($origin, $allowedOrigins, true)) {
                    $allowOriginHeader = $origin;
                }
            } elseif (count($allowedOrigins) === 1) {
                $allowOriginHeader = $allowedOrigins[0];
            }

            if ($allowOriginHeader !== null) {
                header('Access-Control-Allow-Origin: ' . $allowOriginHeader);
                header('Vary: Origin');
            }
        }
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');
        
        // Cabeçalhos de segurança
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Responder a requisições OPTIONS
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }
    
    /**
     * Resposta de sucesso padronizada
     */
    public static function success($data = null, $message = 'Operação realizada com sucesso', $pagination = null) {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c')
        ];
        
        if ($pagination) {
            $response['pagination'] = $pagination;
        }
        
        return json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    
    /**
     * Resposta de erro padronizada
     */
    public static function error($message = 'Erro interno do servidor', $code = 500, $details = null) {
        http_response_code($code);
        
        $response = [
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $code
            ],
            'timestamp' => date('c')
        ];
        
        if ($details && (defined('API_DEBUG') && API_DEBUG)) {
            $response['error']['details'] = $details;
        }
        
        return json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    
    /**
     * Validar método HTTP
     */
    public static function validateMethod($allowedMethods) {
        $method = $_SERVER['REQUEST_METHOD'];
        if (!in_array($method, $allowedMethods)) {
            echo self::error('Método não permitido', 405);
            exit();
        }
    }
    
    /**
     * Obter dados de entrada da requisição
     */
    public static function getInput() {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE && !empty($input)) {
            return null;
        }
        
        // Mesclar com dados POST/GET
        return array_merge($_GET, $_POST, $data ?: []);
    }
    
    /**
     * Validar parâmetros obrigatórios
     */
    public static function validateRequired($data, $required) {
        $missing = [];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            echo self::error('Campos obrigatórios: ' . implode(', ', $missing), 400);
            exit();
        }
    }
    
    /**
     * Sanitizar dados de entrada
     */
    public static function sanitize($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitize'], $data);
        }
        
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validar email
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validar senha
     */
    public static function validatePassword($password) {
        return strlen($password) >= 6;
    }
    
    /**
     * Gerar hash de senha
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Verificar senha
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Verificar se usuário está logado
     */
    public static function requireAuth() {
        if (!isset($_SESSION['user_id'])) {
            echo self::error('Acesso negado. Faça login primeiro.', 401);
            exit();
        }
        
        // Verificar timeout da sessão
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
            session_destroy();
            echo self::error('Sessão expirada. Faça login novamente.', 401);
            exit();
        }
        
        $_SESSION['last_activity'] = time();
    }
    
    /**
     * Verificar se usuário é admin
     */
    public static function requireAdmin() {
        self::requireAuth();
        
        if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
            echo self::error('Acesso negado. Privilégios de administrador necessários.', 403);
            exit();
        }
    }
    
    /**
     * Configurar paginação
     */
    public static function getPagination($page = 1, $perPage = DEFAULT_PAGE_SIZE) {
        $page = max(1, intval($page));
        $perPage = min(MAX_PAGE_SIZE, max(1, intval($perPage)));
        $offset = ($page - 1) * $perPage;
        
        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => $offset
        ];
    }
    
    /**
     * Calcular informações de paginação
     */
    public static function calculatePagination($total, $page, $perPage) {
        return [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => ceil($total / $perPage),
            'has_next' => $page < ceil($total / $perPage),
            'has_prev' => $page > 1
        ];
    }
    
    /**
     * Log de atividades
     */
    public static function logActivity($action, $details = null) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $_SESSION['user_id'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'action' => $action,
            'details' => $details
        ];
        
        // Aqui você pode implementar o log em arquivo ou banco de dados
        error_log('API Activity: ' . json_encode($logData));
    }
}

// Configurar cabeçalhos automaticamente
ApiUtils::setHeaders();

?>
