<?php
/**
 * Middleware de Segurança e Validação
 * KMKZ IPTV API
 */

require_once __DIR__ . '/config.php';

class ApiMiddleware {
    
    /**
     * Rate Limiting - Controle de taxa de requisições
     */
    public static function rateLimit() {
        if (!RATE_LIMIT_ENABLED) {
            return true;
        }
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'rate_limit_' . md5($ip);
        
        // Usar sessão para armazenar dados de rate limiting (em produção, use Redis/Memcached)
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'requests' => 0,
                'window_start' => time()
            ];
        }
        
        $data = $_SESSION[$key];
        $currentTime = time();
        
        // Reset da janela se passou o tempo
        if (($currentTime - $data['window_start']) >= RATE_LIMIT_WINDOW) {
            $_SESSION[$key] = [
                'requests' => 1,
                'window_start' => $currentTime
            ];
            return true;
        }
        
        // Verificar se excedeu o limite
        if ($data['requests'] >= RATE_LIMIT_REQUESTS) {
            http_response_code(429);
            echo ApiUtils::error('Muitas requisições. Tente novamente em ' . 
                (RATE_LIMIT_WINDOW - ($currentTime - $data['window_start'])) . ' segundos.', 429);
            exit();
        }
        
        // Incrementar contador
        $_SESSION[$key]['requests']++;
        return true;
    }
    
    /**
     * Proteção contra força bruta em login
     */
    public static function loginAttemptLimit($identifier) {
        $key = 'login_attempts_' . md5($identifier);
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'attempts' => 0,
                'locked_until' => 0
            ];
        }
        
        $data = $_SESSION[$key];
        
        // Verificar se ainda está bloqueado
        if ($data['locked_until'] > time()) {
            $remainingTime = $data['locked_until'] - time();
            echo ApiUtils::error(
                'Muitas tentativas de login. Tente novamente em ' . 
                ceil($remainingTime / 60) . ' minutos.', 
                429
            );
            exit();
        }
        
        // Reset se passou o tempo de bloqueio
        if ($data['locked_until'] <= time() && $data['attempts'] >= MAX_LOGIN_ATTEMPTS) {
            $_SESSION[$key] = [
                'attempts' => 0,
                'locked_until' => 0
            ];
        }
        
        return true;
    }
    
    /**
     * Registrar tentativa de login falhada
     */
    public static function recordFailedLogin($identifier) {
        $key = 'login_attempts_' . md5($identifier);
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'attempts' => 0,
                'locked_until' => 0
            ];
        }
        
        $_SESSION[$key]['attempts']++;
        
        // Bloquear se atingiu o limite
        if ($_SESSION[$key]['attempts'] >= MAX_LOGIN_ATTEMPTS) {
            $_SESSION[$key]['locked_until'] = time() + LOGIN_LOCKOUT_TIME;
        }
    }
    
    /**
     * Limpar tentativas de login após sucesso
     */
    public static function clearLoginAttempts($identifier) {
        $key = 'login_attempts_' . md5($identifier);
        unset($_SESSION[$key]);
    }
    
    /**
     * Validação de entrada contra XSS e SQL Injection
     */
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        
        // Remover tags HTML perigosas
        $data = strip_tags($data);
        
        // Escapar caracteres especiais
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        
        // Remover caracteres de controle
        $data = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $data);
        
        return trim($data);
    }
    
    /**
     * Validação de CSRF Token
     */
    public static function validateCSRF() {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            return true;
        }
        
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        
        if (!$token || !isset($_SESSION['csrf_token']) || 
            !hash_equals($_SESSION['csrf_token'], $token)) {
            echo ApiUtils::error('Token CSRF inválido', 403);
            exit();
        }
        
        return true;
    }
    
    /**
     * Gerar token CSRF
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validação de tamanho de upload
     */
    public static function validateUploadSize($fileSize) {
        if ($fileSize > MAX_UPLOAD_SIZE) {
            echo ApiUtils::error(
                'Arquivo muito grande. Tamanho máximo: ' . 
                (MAX_UPLOAD_SIZE / 1024 / 1024) . 'MB', 
                413
            );
            exit();
        }
        return true;
    }
    
    /**
     * Validação de extensão de arquivo
     */
    public static function validateFileExtension($filename) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($extension, ALLOWED_EXTENSIONS)) {
            echo ApiUtils::error(
                'Extensão de arquivo não permitida. Permitidas: ' . 
                implode(', ', ALLOWED_EXTENSIONS), 
                400
            );
            exit();
        }
        
        return true;
    }
    
    /**
     * Validação de IP permitido (whitelist)
     */
    public static function validateIP($allowedIPs = []) {
        if (empty($allowedIPs)) {
            return true;
        }
        
        $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // Verificar se está na lista de IPs permitidos
        if (!in_array($clientIP, $allowedIPs)) {
            echo ApiUtils::error('Acesso negado para este IP', 403);
            exit();
        }
        
        return true;
    }
    
    /**
     * Validação de User-Agent
     */
    public static function validateUserAgent() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Lista de user agents suspeitos
        $suspiciousAgents = [
            'sqlmap',
            'nikto',
            'nmap',
            'masscan',
            'zap',
            'burp'
        ];
        
        foreach ($suspiciousAgents as $suspicious) {
            if (stripos($userAgent, $suspicious) !== false) {
                echo ApiUtils::error('User-Agent não permitido', 403);
                exit();
            }
        }
        
        return true;
    }
    
    /**
     * Log de segurança
     */
    public static function logSecurity($event, $details = []) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'user_id' => $_SESSION['user_id'] ?? null,
            'details' => $details
        ];
        
        // Log em arquivo (em produção, considere usar um sistema de log mais robusto)
        error_log('SECURITY: ' . json_encode($logData));
    }
    
    /**
     * Middleware principal - aplicar todas as validações
     */
    public static function apply($options = []) {
        // Configurar cabeçalhos padrão (JSON, UTF-8, CORS)
        ApiUtils::setHeaders();

        // Rate limiting
        if (!isset($options['skip_rate_limit']) || !$options['skip_rate_limit']) {
            self::rateLimit();
        }
        
        // Validação de User-Agent
        if (!isset($options['skip_user_agent']) || !$options['skip_user_agent']) {
            self::validateUserAgent();
        }
        
        // Validação de IP (se especificado)
        if (isset($options['allowed_ips'])) {
            self::validateIP($options['allowed_ips']);
        }
        
        // CSRF para métodos que modificam dados
        if (isset($options['csrf']) && $options['csrf']) {
            self::validateCSRF();
        }
        
        // Log da requisição
        self::logSecurity('api_request', [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'query' => $_GET
        ]);
    }
}

/**
 * Classe para validação de dados específicos
 */
class DataValidator {
    
    /**
     * Validar dados de usuário
     */
    public static function validateUser($data) {
        $errors = [];
        
        // Nome
        if (empty($data['name']) || strlen($data['name']) < 2) {
            $errors[] = 'Nome deve ter pelo menos 2 caracteres';
        }
        
        // Email
        if (empty($data['email']) || !ApiUtils::validateEmail($data['email'])) {
            $errors[] = 'Email inválido';
        }
        
        // Senha (apenas para criação/alteração)
        if (isset($data['password']) && !ApiUtils::validatePassword($data['password'])) {
            $errors[] = 'Senha deve ter pelo menos 6 caracteres';
        }
        
        // Telefone (opcional)
        if (!empty($data['phone']) && !preg_match('/^\(?\d{2}\)?[\s-]?\d{4,5}[\s-]?\d{4}$/', $data['phone'])) {
            $errors[] = 'Formato de telefone inválido';
        }
        
        return $errors;
    }
    
    /**
     * Validar dados de plano
     */
    public static function validatePlan($data) {
        $errors = [];
        
        // Nome
        if (empty($data['name']) || strlen($data['name']) < 3) {
            $errors[] = 'Nome do plano deve ter pelo menos 3 caracteres';
        }
        
        // Preço
        if (!isset($data['price']) || !is_numeric($data['price']) || $data['price'] < 0) {
            $errors[] = 'Preço deve ser um valor numérico válido';
        }
        
        // Duração
        if (!isset($data['duration_months']) || !is_numeric($data['duration_months']) || $data['duration_months'] < 1) {
            $errors[] = 'Duração deve ser pelo menos 1 mês';
        }
        
        // Dispositivos
        if (!isset($data['max_devices']) || !is_numeric($data['max_devices']) || $data['max_devices'] < 1) {
            $errors[] = 'Número máximo de dispositivos deve ser pelo menos 1';
        }
        
        return $errors;
    }
    
    /**
     * Validar dados de pagamento
     */
    public static function validatePayment($data) {
        $errors = [];
        
        // Método de pagamento
        $allowedMethods = ['credit_card', 'debit_card', 'pix', 'boleto', 'paypal'];
        if (empty($data['payment_method']) || !in_array($data['payment_method'], $allowedMethods)) {
            $errors[] = 'Método de pagamento inválido';
        }
        
        // Valor
        if (!isset($data['amount']) || !is_numeric($data['amount']) || $data['amount'] <= 0) {
            $errors[] = 'Valor deve ser maior que zero';
        }
        
        return $errors;
    }
}

?>