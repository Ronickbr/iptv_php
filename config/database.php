<?php

function getProjectRootPath() {
    return dirname(__DIR__);
}

function getEnvFilePath() {
    return getProjectRootPath() . DIRECTORY_SEPARATOR . '.env';
}

function loadDotEnvIfPresent() {
    $envPath = getEnvFilePath();
    if (!is_file($envPath) || !is_readable($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
            continue;
        }

        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }

        $key = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));

        if ($key === '') {
            continue;
        }

        if ($value !== '' && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }

        if (getenv($key) === false) {
            putenv($key . '=' . $value);
        }
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
        }
        if (!array_key_exists($key, $_SERVER)) {
            $_SERVER[$key] = $value;
        }
    }
}

function isApplicationInstalled() {
    return is_file(getEnvFilePath()) && is_readable(getEnvFilePath());
}

function detectInstallUrl() {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/install.php');
    $basePath = preg_replace('#/api/.*$#', '', $scriptName);
    $basePath = preg_replace('#/[^/]+$#', '', $basePath);
    $basePath = rtrim((string)$basePath, '/');

    return $scheme . '://' . $host . $basePath . '/install.php';
}

function isInstallRequest() {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    return basename($scriptName) === 'install.php';
}

function isApiRequest() {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    return str_contains($scriptName, '/api/');
}

function ensureApplicationInstalled() {
    if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
        return;
    }

    if (isApplicationInstalled() || isInstallRequest()) {
        return;
    }

    $installUrl = detectInstallUrl();

    if (isApiRequest()) {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Sistema ainda nao instalado. Acesse o instalador antes de usar a API.',
            'install_url' => $installUrl
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Location: ' . $installUrl);
    exit;
}

function getRequiredDatabaseTables() {
    return ['users', 'plans', 'subscriptions'];
}

function getDatabaseReadinessIssue() {
    static $cachedIssue = false;

    if ($cachedIssue !== false) {
        return $cachedIssue;
    }

    try {
        $database = new Database();
        $connection = $database->getConnection();
    } catch (Throwable $exception) {
        $connection = null;
    }

    if (!$connection instanceof PDO) {
        $cachedIssue = 'Nao foi possivel conectar ao banco com as credenciais atuais do arquivo .env.';
        return $cachedIssue;
    }

    $databaseName = (string)envValue('DB_NAME', '');
    if ($databaseName === '') {
        $cachedIssue = 'O arquivo .env nao possui um nome de banco valido em DB_NAME.';
        return $cachedIssue;
    }

    try {
        $statement = $connection->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :schema AND table_name = :table_name'
        );

        foreach (getRequiredDatabaseTables() as $tableName) {
            $statement->execute([
                ':schema' => $databaseName,
                ':table_name' => $tableName
            ]);

            if ((int)$statement->fetchColumn() === 0) {
                $cachedIssue = 'O banco foi encontrado, mas a estrutura principal ainda nao foi criada por completo.';
                return $cachedIssue;
            }
        }
    } catch (Throwable $exception) {
        $cachedIssue = 'Nao foi possivel validar a estrutura do banco de dados.';
        return $cachedIssue;
    }

    $cachedIssue = null;
    return $cachedIssue;
}

function getRecoveryInstallUrl() {
    $installUrl = detectInstallUrl();

    if (!isApplicationInstalled()) {
        return $installUrl;
    }

    return $installUrl . (str_contains($installUrl, '?') ? '&' : '?') . 'force=1';
}

function renderIncompleteInstallationPage($message, $installUrl) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');

    $safeMessage = htmlspecialchars((string)$message, ENT_QUOTES, 'UTF-8');
    $safeInstallUrl = htmlspecialchars((string)$installUrl, ENT_QUOTES, 'UTF-8');

    echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalacao Incompleta</title>
    <style>
        body{margin:0;padding:24px;font-family:Arial,sans-serif;background:#0f172a;color:#0f172a}
        .wrap{max-width:760px;margin:40px auto;background:#fff;border-radius:18px;padding:32px;box-shadow:0 20px 50px rgba(15,23,42,.25)}
        h1{margin-top:0;color:#0f172a}
        p{line-height:1.6;color:#334155}
        .alert{background:#fff7ed;border:1px solid #fdba74;color:#9a3412;padding:16px 18px;border-radius:12px;margin:20px 0}
        .actions{margin-top:24px;display:flex;gap:12px;flex-wrap:wrap}
        .btn{display:inline-block;padding:14px 18px;border-radius:12px;text-decoration:none;font-weight:bold}
        .btn-primary{background:#2563eb;color:#fff}
        .btn-secondary{background:#e2e8f0;color:#0f172a}
        code{background:#f1f5f9;padding:2px 6px;border-radius:6px}
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Instalacao incompleta detectada</h1>
        <p>O sistema encontrou um arquivo <code>.env</code>, mas a estrutura do banco ainda nao esta pronta para uso.</p>
        <div class="alert"><strong>Detalhe:</strong> ' . $safeMessage . '</div>
        <p>Use o instalador para concluir ou reparar a configuracao do banco de dados e recriar o usuario administrador, se necessario.</p>
        <div class="actions">
            <a class="btn btn-primary" href="' . $safeInstallUrl . '">Abrir instalador</a>
            <a class="btn btn-secondary" href="' . $safeInstallUrl . '">Reinstalar com force=1</a>
        </div>
    </div>
</body>
</html>';
    exit;
}

function ensureApplicationReady() {
    ensureApplicationInstalled();

    if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg' || isInstallRequest() || !isApplicationInstalled()) {
        return;
    }

    $issue = getDatabaseReadinessIssue();
    if ($issue === null) {
        return;
    }

    $installUrl = getRecoveryInstallUrl();

    if (isApiRequest()) {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Instalacao incompleta detectada. Finalize o instalador antes de usar a API.',
            'details' => $issue,
            'install_url' => $installUrl
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    renderIncompleteInstallationPage($issue, $installUrl);
}

function envValue($key, $default = null) {
    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }
    if (isset($_ENV[$key])) {
        return $_ENV[$key];
    }
    if (isset($_SERVER[$key])) {
        return $_SERVER[$key];
    }
    return $default;
}

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    private $conn;
    
    public function __construct() {
        loadDotEnvIfPresent();
        $this->host = envValue('DB_HOST', 'localhost');
        $this->db_name = envValue('DB_NAME', 'kmkz_iptv');
        $this->username = envValue('DB_USER', 'root');
        $this->password = envValue('DB_PASS', '');
        $this->port = envValue('DB_PORT', null);
    }
    
    public function getConnection() {
        $this->conn = null;
        
        try {
            $portPart = '';
            if ($this->port !== null && $this->port !== '') {
                $portPart = ';port=' . $this->port;
            }
            $this->conn = new PDO(
                "mysql:host=" . $this->host . $portPart . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->exec("set names utf8mb4");
        } catch(PDOException $exception) {
            error_log("Erro na conexão: " . $exception->getMessage());
        }
        
        return $this->conn;
    }
}

// Função helper para obter conexão
function getDB() {
    $database = new Database();
    return $database->getConnection();
}

ensureApplicationReady();

?>
