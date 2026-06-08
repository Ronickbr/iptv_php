<?php

function loadDotEnvIfPresent() {
    $projectRoot = dirname(__DIR__);
    $envPath = $projectRoot . DIRECTORY_SEPARATOR . '.env';
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

?>
