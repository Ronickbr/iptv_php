<?php
/**
 * Endpoint de Autenticação
 * KMKZ IPTV API
 * 
 * Endpoints disponíveis:
 * POST /auth.php?action=login - Fazer login
 * POST /auth.php?action=logout - Fazer logout
 * POST /auth.php?action=register - Registrar usuário
 * GET /auth.php?action=check - Verificar sessão
 * POST /auth.php?action=forgot_password - Recuperar senha
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/middleware.php';

// Aplicar middleware de segurança
ApiMiddleware::apply();

// Obter método HTTP
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Obter ação
$action = $_GET['action'] ?? '';
$input = ApiUtils::getInput();

// Conectar ao banco
$db = getDB();
if (!$db) {
    echo ApiUtils::error('Erro de conexão com o banco de dados');
    exit();
}

switch ($method) {
    case 'POST':
        switch ($action) {
            case 'login':
                ApiUtils::validateMethod(['POST']);
                
                $email = trim((string)($input['email'] ?? ''));
                $password = (string)($input['password'] ?? '');

                if ($email === '' || $password === '') {
                    echo ApiUtils::error('Email e senha são obrigatórios', 400);
                    exit();
                }
                if (!ApiUtils::validateEmail($email)) {
                    echo ApiUtils::error('Email inválido', 400);
                    exit();
                }
                if (!ApiUtils::validatePassword($password)) {
                    echo ApiUtils::error('Senha inválida', 400);
                    exit();
                }

                $identifier = strtolower($email);
                ApiMiddleware::loginAttemptLimit($identifier);
                
                try {
                    $stmt = $db->prepare("SELECT id, name, email, password, user_type FROM users WHERE email = ? AND status = 'active'");
                    $stmt->execute([$email]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($user && password_verify($password, $user['password'])) {
                        ApiMiddleware::clearLoginAttempts($identifier);

                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_type'] = $user['user_type'];
                        $_SESSION['last_activity'] = time();
                        
                        echo ApiUtils::success([
                            'user' => [
                                'id' => $user['id'],
                                'name' => $user['name'],
                                'email' => $user['email'],
                                'user_type' => $user['user_type']
                            ]
                        ], 'Login realizado com sucesso');
                    } else {
                        ApiMiddleware::recordFailedLogin($identifier);
                        echo ApiUtils::error('Email ou senha inválidos', 401);
                    }
                } catch (Exception $e) {
                    echo ApiUtils::error('Erro interno do servidor', 500);
                }
                break;
                
            case 'forgot_password':
                ApiUtils::validateMethod(['POST']);

                $email = trim((string)($input['email'] ?? ''));
                if ($email === '' || !ApiUtils::validateEmail($email)) {
                    echo ApiUtils::error('Email inválido', 400);
                    exit();
                }

                $token = null;
                $resetUrl = null;
                $mailSent = false;

                try {
                    $stmt = $db->prepare("SELECT id, email, status FROM users WHERE email = ? AND status = 'active' LIMIT 1");
                    $stmt->execute([$email]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($user) {
                        $token = bin2hex(random_bytes(32));
                        $tokenHash = hash('sha256', $token);
                        $expiresAt = (new DateTimeImmutable('now'))->add(new DateInterval('PT30M'))->format('Y-m-d H:i:s');

                        $requestedIp = $_SERVER['REMOTE_ADDR'] ?? null;
                        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
                        if (is_string($userAgent) && function_exists('mb_substr')) {
                            $userAgent = mb_substr($userAgent, 0, 255);
                        } elseif (is_string($userAgent)) {
                            $userAgent = substr($userAgent, 0, 255);
                        }

                        $insert = $db->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at, requested_ip, user_agent) VALUES (?, ?, ?, ?, ?)");
                        $insert->execute([(int)$user['id'], $tokenHash, $expiresAt, $requestedIp, $userAgent]);

                        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                            (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
                        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                        $baseUrl = rtrim((string)(getenv('APP_URL') ?: ($scheme . '://' . $host)), '/');
                        $resetUrl = $baseUrl . '/login.php?token=' . urlencode($token);

                        $from = (string)(getenv('MAIL_FROM') ?: '');
                        $appName = (string)(getenv('APP_NAME') ?: 'KMKZ IPTV');

                        if ($from !== '' && function_exists('mail')) {
                            $subject = $appName . ' - Redefinição de senha';
                            $body = "Olá!\n\nRecebemos uma solicitação para redefinir sua senha.\n\nAbra o link abaixo para criar uma nova senha (válido por 30 minutos):\n{$resetUrl}\n\nSe você não solicitou, ignore este email.\n";
                            $headers = [];
                            $headers[] = 'From: ' . $from;
                            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
                            $mailSent = @mail($email, $subject, $body, implode("\r\n", $headers));
                        }
                    }
                } catch (Exception $e) {
                    if (defined('API_DEBUG') && API_DEBUG) {
                        echo ApiUtils::error('Erro interno do servidor', 500, $e->getMessage());
                        exit();
                    }
                    echo ApiUtils::error('Recuperação de senha indisponível no momento', 500);
                    exit();
                }

                $data = [
                    'mail_sent' => $mailSent
                ];
                if (defined('API_DEBUG') && API_DEBUG && $resetUrl) {
                    $data['reset_url'] = $resetUrl;
                }

                echo ApiUtils::success($data, 'Se o email estiver cadastrado, você receberá um link para redefinir sua senha.');
                break;

            case 'reset_password':
                ApiUtils::validateMethod(['POST']);

                $token = trim((string)($input['token'] ?? ''));
                $password = (string)($input['password'] ?? '');

                if ($token === '' || $password === '') {
                    echo ApiUtils::error('Token e senha são obrigatórios', 400);
                    exit();
                }
                if (!ApiUtils::validatePassword($password)) {
                    echo ApiUtils::error('Senha inválida', 400);
                    exit();
                }

                try {
                    $tokenHash = hash('sha256', $token);

                    $stmt = $db->prepare("SELECT pr.id, pr.user_id 
                        FROM password_resets pr
                        WHERE pr.token_hash = ?
                          AND pr.used_at IS NULL
                          AND pr.expires_at > NOW()
                        LIMIT 1");
                    $stmt->execute([$tokenHash]);
                    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$reset) {
                        echo ApiUtils::error('Link inválido ou expirado. Solicite um novo.', 400);
                        exit();
                    }

                    $db->beginTransaction();
                    $newHash = ApiUtils::hashPassword($password);
                    $updateUser = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                    $updateUser->execute([$newHash, (int)$reset['user_id']]);

                    $markUsed = $db->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?");
                    $markUsed->execute([(int)$reset['id']]);

                    $db->commit();

                    echo ApiUtils::success(null, 'Senha alterada com sucesso. Faça login novamente.');
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    echo ApiUtils::error('Erro interno do servidor', 500, $e->getMessage());
                }
                break;

            case 'register':
                ApiUtils::validateMethod(['POST']);
                
                $name = trim((string)($input['name'] ?? ''));
                $email = trim((string)($input['email'] ?? ''));
                $password = (string)($input['password'] ?? '');
                $phone = trim((string)($input['phone'] ?? ''));

                if ($name === '' || $email === '' || $password === '') {
                    echo ApiUtils::error('Campos obrigatórios: name, email, password', 400);
                    exit();
                }
                $nameLen = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
                if ($nameLen < 2 || $nameLen > 100) {
                    echo ApiUtils::error('Nome inválido', 400);
                    exit();
                }
                if (!ApiUtils::validateEmail($email)) {
                    echo ApiUtils::error('Email inválido', 400);
                    exit();
                }
                if (!ApiUtils::validatePassword($password)) {
                    echo ApiUtils::error('Senha inválida', 400);
                    exit();
                }
                $phoneLen = function_exists('mb_strlen') ? mb_strlen($phone) : strlen($phone);
                if ($phone !== '' && $phoneLen > 20) {
                    echo ApiUtils::error('Telefone inválido', 400);
                    exit();
                }
                
                try {
                    // Verificar se email já existe
                    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        echo ApiUtils::error('Email já está em uso', 400);
                    }
                    
                    // Criar usuário
                    $hashedPassword = ApiUtils::hashPassword($password);
                    $stmt = $db->prepare("INSERT INTO users (name, email, password, phone, created_at) VALUES (?, ?, ?, ?, NOW())");
                    
                    if ($stmt->execute([$name, $email, $hashedPassword, $phone !== '' ? $phone : null])) {
                        $userId = $db->lastInsertId();
                        
                        // Fazer login automático
                        $_SESSION['user_id'] = $userId;
                        $_SESSION['user_name'] = $name;
                        $_SESSION['user_email'] = $email;
                        $_SESSION['user_type'] = 'user';
                        $_SESSION['last_activity'] = time();
                        
                        echo ApiUtils::success([
                            'user' => [
                                'id' => $userId,
                                'name' => $name,
                                'email' => $email,
                                'user_type' => 'user'
                            ]
                        ], 'Usuário criado com sucesso');
                    } else {
                        echo ApiUtils::error('Erro ao criar usuário', 500);
                    }
                } catch (Exception $e) {
                    echo ApiUtils::error('Erro interno do servidor', 500);
                }
                break;
                
            case 'logout':
                ApiUtils::validateMethod(['POST']);
                
                session_destroy();
                echo ApiUtils::success(null, 'Logout realizado com sucesso');
                break;
                
            default:
                echo ApiUtils::error('Ação inválida', 400);
                break;
        }
        break;
        
    case 'GET':
        switch ($action) {
            case 'profile':
                ApiUtils::validateMethod(['GET']);
                
                ApiUtils::requireAuth();
                $userId = $_SESSION['user_id'];
                
                try {
                    $stmt = $db->prepare("SELECT id, name, email, phone, points, created_at, user_type FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($user) {
                        // Formatar dados
                        $user['created_at'] = ApiUtils::formatDate($user['created_at']);
                        $user['points'] = intval($user['points']);
                        
                        echo ApiUtils::success(['user' => $user]);
                    } else {
                        echo ApiUtils::error('Usuário não encontrado', 404);
                    }
                } catch (Exception $e) {
                    echo ApiUtils::error('Erro interno do servidor', 500);
                }
                break;
                
            case 'check':
                ApiUtils::validateMethod(['GET']);
                
                if (isset($_SESSION['user_id'])) {
                    // Verificar se sessão não expirou
                    if (isset($_SESSION['last_activity'])) {
                        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
                            session_destroy();
                            echo ApiUtils::success([
                                'authenticated' => false,
                                'reason' => 'session_expired'
                            ]);
                        }
                    }
                    
                    $_SESSION['last_activity'] = time();
                    
                    echo ApiUtils::success([
                        'authenticated' => true,
                        'user' => [
                            'id' => $_SESSION['user_id'],
                            'name' => $_SESSION['user_name'],
                            'email' => $_SESSION['user_email'],
                            'user_type' => $_SESSION['user_type'] ?? 'user'
                        ]
                    ]);
                } else {
                    echo ApiUtils::success(['authenticated' => false]);
                }
                break;
                
            default:
                echo ApiUtils::error('Ação inválida', 400);
                break;
        }
        break;
        
    default:
        echo ApiUtils::error('Método não permitido', 405);
        break;
}
?>
