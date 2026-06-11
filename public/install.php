<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$envFilePath = $projectRoot . DIRECTORY_SEPARATOR . '.env';
$sqlFilePath = $projectRoot . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'init.sql';
$storageRoot = $projectRoot . DIRECTORY_SEPARATOR . 'storage';
$envExists = is_file($envFilePath);
$forceInstall = isset($_GET['force']) && $_GET['force'] === '1';
$locked = $envExists && !$forceInstall;
$success = false;
$message = '';
$error = null;

$config = [
    'db_host' => 'localhost',
    'db_port' => '3306',
    'db_name' => 'kmkz_iptv',
    'db_user' => '',
    'db_pass' => '',
    'admin_name' => 'Administrador',
    'admin_email' => 'admin@seusite.com',
    'admin_password' => '',
    'app_name' => 'KMKZ IPTV',
    'app_url' => detectBaseUrl(),
    'timezone' => 'America/Sao_Paulo',
    'cors_allowed_origins' => detectBaseUrl(),
    'ga4_measurement_id' => '',
    'meta_pixel_id' => ''
];

$statusChecks = buildStatusChecks($projectRoot, $envFilePath, $sqlFilePath);
$statusOk = !in_array(false, array_column($statusChecks, 'ok'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$locked) {
    $config = array_merge($config, collectFormValues($config));

    try {
        validateConfig($config, $statusChecks);

        $pdo = createDatabaseConnection($config);
        ensureDatabaseSelected($pdo, $config['db_name']);
        $hasRequiredTables = databaseHasTables($pdo, $config['db_name'], getRequiredDatabaseTables());

        if (is_file($sqlFilePath) && is_readable($sqlFilePath)) {
            if ($forceInstall) {
                resetDatabaseSchema($pdo, $config['db_name']);
                executeSqlScript($pdo, $sqlFilePath);
            } elseif (!$hasRequiredTables) {
                executeSqlScript($pdo, $sqlFilePath);
            }
        } else {
            if (!$hasRequiredTables) {
                throw new RuntimeException('Arquivo SQL nao encontrado no servidor e o banco ainda nao possui a estrutura. Envie a pasta database/ (com init.sql) ou importe o SQL manualmente e tente novamente.');
            }
        }
        removeDemoUsers($pdo, $config['admin_email']);
        createAdminUser($pdo, $config);
        updateSystemSettings($pdo, $config);
        createEnvFile($config, $envFilePath);
        createDirectories($storageRoot);

        $success = true;
        $message = 'Instalacao concluida com sucesso.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

function collectFormValues(array $defaults): array
{
    $values = [];

    foreach ($defaults as $field => $default) {
        $values[$field] = trim((string)($_POST[$field] ?? $default));
    }

    return $values;
}

function detectBaseUrl(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/install.php');
    $basePath = rtrim(str_replace('/install.php', '', $scriptName), '/');

    return $scheme . '://' . $host . $basePath;
}

function buildStatusChecks(string $projectRoot, string $envFilePath, string $sqlFilePath): array
{
    $envDirectory = dirname($envFilePath);

    return [
        [
            'label' => 'PHP 8+',
            'ok' => version_compare(PHP_VERSION, '8.0.0', '>='),
            'detail' => 'Versao atual: ' . PHP_VERSION
        ],
        [
            'label' => 'Extensao PDO MySQL',
            'ok' => extension_loaded('pdo_mysql'),
            'detail' => 'Necessaria para conectar ao MySQL.'
        ],
        [
            'label' => 'Arquivo SQL',
            'ok' => is_file($sqlFilePath) && is_readable($sqlFilePath),
            'detail' => $sqlFilePath
        ],
        [
            'label' => 'Permissao para gravar .env',
            'ok' => (is_file($envFilePath) && is_writable($envFilePath))
                || (!is_file($envFilePath) && is_dir($envDirectory) && is_writable($envDirectory)),
            'detail' => $envFilePath
        ],
        [
            'label' => 'Permissao para criar storage',
            'ok' => is_writable($projectRoot),
            'detail' => $projectRoot
        ]
    ];
}

function validateConfig(array $config, array $statusChecks): void
{
    $required = [
        'db_host',
        'db_port',
        'db_name',
        'db_user',
        'admin_name',
        'admin_email',
        'admin_password',
        'app_name',
        'app_url',
        'timezone'
    ];

    foreach ($required as $field) {
        if ($config[$field] === '') {
            throw new RuntimeException('Preencha o campo obrigatorio: ' . $field);
        }
    }

    if (!filter_var($config['admin_email'], FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Informe um email de administrador valido.');
    }

    if (strlen($config['admin_password']) < 6) {
        throw new RuntimeException('A senha do administrador precisa ter pelo menos 6 caracteres.');
    }

    if (!filter_var($config['app_url'], FILTER_VALIDATE_URL)) {
        throw new RuntimeException('Informe uma URL valida para a aplicacao.');
    }

    if (!ctype_digit($config['db_port'])) {
        throw new RuntimeException('A porta do banco deve conter apenas numeros.');
    }

    foreach ($statusChecks as $check) {
        if (!$check['ok']) {
            if (($check['label'] ?? '') === 'Arquivo SQL') {
                continue;
            }
            throw new RuntimeException('Corrija os pre-requisitos antes de instalar: ' . $check['label']);
        }
    }
}

function createDatabaseConnection(array $config): PDO
{
    try {
        return new PDO(
            sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['db_host'], $config['db_port']),
            $config['db_user'],
            $config['db_pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'
            ]
        );
    } catch (PDOException $e) {
        throw new RuntimeException('Nao foi possivel conectar ao banco: ' . $e->getMessage(), 0, $e);
    }
}

function ensureDatabaseSelected(PDO $pdo, string $dbName): void
{
    $quotedDbName = '`' . str_replace('`', '``', $dbName) . '`';

    try {
        try {
            $pdo->exec('CREATE DATABASE IF NOT EXISTS ' . $quotedDbName . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        } catch (PDOException $e) {
            // Em algumas hospedagens o usuario nao tem permissao para criar o banco.
        }

        $pdo->exec('USE ' . $quotedDbName);
    } catch (PDOException $e) {
        throw new RuntimeException(
            'Nao foi possivel selecionar o banco "' . $dbName . '". Crie-o no painel da hospedagem e tente novamente.'
        );
    }
}

function executeSqlScript(PDO $pdo, string $sqlFile): void
{
    $lines = file($sqlFile, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new RuntimeException('Nao foi possivel ler o arquivo SQL de instalacao.');
    }

    $delimiter = ';';
    $statement = '';

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '--')) {
            continue;
        }

        if (preg_match('/^DELIMITER\s+(.+)$/i', $trimmed, $matches)) {
            $delimiter = $matches[1];
            continue;
        }

        $statement .= $line . "\n";
        $preparedStatement = trim($statement);

        if ($preparedStatement === '' || !endsWithDelimiter($preparedStatement, $delimiter)) {
            continue;
        }

        $sql = trim(substr($preparedStatement, 0, -strlen($delimiter)));
        $statement = '';

        if ($sql === '' || shouldSkipStatement($sql)) {
            continue;
        }

        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            if (isIgnorableSqlError($e, $sql)) {
                continue;
            }
            throw new RuntimeException('Erro ao executar a estrutura do banco: ' . $e->getMessage(), 0, $e);
        }
    }
}

function endsWithDelimiter(string $statement, string $delimiter): bool
{
    return $delimiter !== '' && substr($statement, -strlen($delimiter)) === $delimiter;
}

function shouldSkipStatement(string $sql): bool
{
    $normalized = ltrim($sql);

    return preg_match('/^CREATE\s+DATABASE\b/i', $normalized) === 1
        || preg_match('/^USE\b/i', $normalized) === 1;
}

function getRequiredDatabaseTables(): array
{
    return ['users', 'plans', 'subscriptions'];
}

function databaseHasTables(PDO $pdo, string $dbName, array $tables): bool
{
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :schema AND table_name = :table');
        foreach ($tables as $table) {
            $stmt->execute([
                ':schema' => $dbName,
                ':table' => $table
            ]);
            if ((int)$stmt->fetchColumn() === 0) {
                return false;
            }
        }
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function resetDatabaseSchema(PDO $pdo, string $dbName): void
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

    $stmt = $pdo->prepare('SELECT table_name, table_type FROM information_schema.tables WHERE table_schema = :schema');
    $stmt->execute([':schema' => $dbName]);
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($tables as $row) {
        $name = (string)($row['table_name'] ?? '');
        $type = (string)($row['table_type'] ?? '');
        if ($name === '') {
            continue;
        }

        $quoted = '`' . str_replace('`', '``', $name) . '`';

        if (strcasecmp($type, 'VIEW') === 0) {
            $pdo->exec('DROP VIEW IF EXISTS ' . $quoted);
        } else {
            $pdo->exec('DROP TABLE IF EXISTS ' . $quoted);
        }
    }

    $stmt = $pdo->prepare('SELECT trigger_name FROM information_schema.triggers WHERE trigger_schema = :schema');
    $stmt->execute([':schema' => $dbName]);
    $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($triggers as $row) {
        $name = (string)($row['trigger_name'] ?? '');
        if ($name === '') {
            continue;
        }
        $quoted = '`' . str_replace('`', '``', $name) . '`';
        $pdo->exec('DROP TRIGGER IF EXISTS ' . $quoted);
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
}

function isIgnorableSqlError(PDOException $exception, string $sql): bool
{
    $errorInfo = $exception->errorInfo ?? null;
    $driverCode = is_array($errorInfo) && isset($errorInfo[1]) ? (int)$errorInfo[1] : 0;
    $message = strtolower($exception->getMessage());
    $normalizedSql = ltrim($sql);

    $isCreateStatement = preg_match('/^(CREATE)\s+/i', $normalizedSql) === 1;
    if (!$isCreateStatement) {
        return false;
    }

    if (str_contains($message, 'already exists')) {
        return true;
    }

    return in_array($driverCode, [1050, 1061, 1359], true);
}

function removeDemoUsers(PDO $pdo, string $adminEmail): void
{
    $emailsToRemove = ['user@test.com'];

    if (strcasecmp($adminEmail, 'admin@kmkz.com') !== 0) {
        $emailsToRemove[] = 'admin@kmkz.com';
    }

    $placeholders = implode(',', array_fill(0, count($emailsToRemove), '?'));
    $stmt = $pdo->prepare('DELETE FROM users WHERE email IN (' . $placeholders . ')');
    $stmt->execute($emailsToRemove);
}

function createAdminUser(PDO $pdo, array $config): void
{
    $hashedPassword = password_hash($config['admin_password'], PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        'INSERT INTO users (name, email, password, user_type, status, points, created_at, updated_at)
         VALUES (:name, :email, :password, "admin", "active", 0, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            password = VALUES(password),
            user_type = "admin",
            status = "active"'
    );

    try {
        $stmt->execute([
            ':name' => $config['admin_name'],
            ':email' => $config['admin_email'],
            ':password' => $hashedPassword
        ]);
    } catch (PDOException $e) {
        throw new RuntimeException('Nao foi possivel criar o administrador: ' . $e->getMessage(), 0, $e);
    }
}

function updateSystemSettings(PDO $pdo, array $config): void
{
    $settings = [
        'site_name' => [$config['app_name'], 'string', 'Nome do site'],
        'site_url' => [$config['app_url'], 'string', 'URL do site'],
        'admin_email' => [$config['admin_email'], 'string', 'Email do administrador'],
        'timezone' => [$config['timezone'], 'string', 'Timezone padrao da aplicacao']
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO system_settings (setting_key, setting_value, setting_type, description)
         VALUES (:key, :value, :type, :description)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            setting_type = VALUES(setting_type),
            description = VALUES(description)'
    );

    foreach ($settings as $key => [$value, $type, $description]) {
        $stmt->execute([
            ':key' => $key,
            ':value' => $value,
            ':type' => $type,
            ':description' => $description
        ]);
    }
}

function createEnvFile(array $config, string $envFilePath): void
{
    $envLines = [
        '# Configuracoes do Banco de Dados',
        buildEnvLine('DB_HOST', $config['db_host']),
        buildEnvLine('DB_NAME', $config['db_name']),
        buildEnvLine('DB_USER', $config['db_user']),
        buildEnvLine('DB_PASS', $config['db_pass']),
        buildEnvLine('DB_PORT', $config['db_port']),
        '',
        '# Configuracoes da Aplicacao',
        buildEnvLine('APP_NAME', $config['app_name']),
        'APP_ENV=production',
        'APP_DEBUG=false',
        buildEnvLine('APP_URL', rtrim($config['app_url'], '/')),
        buildEnvLine('TIMEZONE', $config['timezone']),
        '',
        '# Configuracoes da API',
        'API_VERSION=1.0',
        buildEnvLine('API_BASE_URL', rtrim($config['app_url'], '/')),
        'API_VERIFY_SSL=1',
        'API_DEBUG=0',
        buildEnvLine('CORS_ALLOWED_ORIGINS', $config['cors_allowed_origins']),
        '',
        '# Configuracoes de seguranca',
        'SESSION_TIMEOUT=3600',
        'MAX_LOGIN_ATTEMPTS=5',
        'RATE_LIMIT_REQUESTS=60',
        'RATE_LIMIT_WINDOW=1',
        '',
        '# Integracoes opcionais',
        buildEnvLine('GA4_MEASUREMENT_ID', $config['ga4_measurement_id']),
        buildEnvLine('META_PIXEL_ID', $config['meta_pixel_id']),
        '',
        '# Controle de instalacao',
        'ALLOW_INSTALL=0',
        'ALLOW_MIGRATIONS=0'
    ];

    $content = implode(PHP_EOL, $envLines) . PHP_EOL;

    if (file_put_contents($envFilePath, $content) === false) {
        throw new RuntimeException('Nao foi possivel criar o arquivo .env.');
    }
}

function buildEnvLine(string $key, string $value): string
{
    $escaped = str_replace(['\\', '"', "\r", "\n"], ['\\\\', '\"', '', '\n'], $value);
    return $key . '="' . $escaped . '"';
}

function createDirectories(string $storageRoot): void
{
    $directories = [
        $storageRoot,
        $storageRoot . DIRECTORY_SEPARATOR . 'logs',
        $storageRoot . DIRECTORY_SEPARATOR . 'uploads',
        $storageRoot . DIRECTORY_SEPARATOR . 'cache',
        $storageRoot . DIRECTORY_SEPARATOR . 'backups'
    ];

    foreach ($directories as $directory) {
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Nao foi possivel criar o diretorio: ' . $directory);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador KMKZ IPTV</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #10182b;
            color: #172033;
            padding: 32px 16px;
        }

        .page {
            max-width: 960px;
            margin: 0 auto;
            display: grid;
            gap: 24px;
        }

        .card {
            background: #ffffff;
            border-radius: 18px;
            padding: 28px;
            box-shadow: 0 14px 40px rgba(5, 17, 41, 0.22);
        }

        h1, h2, h3, p {
            margin-top: 0;
        }

        .intro {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            align-items: flex-start;
        }

        .intro-badge {
            background: #eaf2ff;
            color: #1747a6;
            border-radius: 999px;
            padding: 8px 14px;
            font-size: 14px;
            white-space: nowrap;
        }

        .alert {
            border-radius: 14px;
            padding: 16px 18px;
            margin-bottom: 20px;
        }

        .alert.error {
            background: #fff0f0;
            color: #8b1d1d;
            border: 1px solid #ffc8c8;
        }

        .alert.success {
            background: #eefbf1;
            color: #1a6f32;
            border: 1px solid #bfe8cb;
        }

        .alert.locked {
            background: #fff8e7;
            color: #7d5b00;
            border: 1px solid #f5d68a;
        }

        .checks {
            display: grid;
            gap: 12px;
        }

        .check {
            border: 1px solid #dfe6f1;
            border-radius: 12px;
            padding: 14px 16px;
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
        }

        .check strong {
            display: block;
            margin-bottom: 4px;
        }

        .check small {
            color: #5a6477;
        }

        .status-ok {
            color: #18763b;
            font-weight: bold;
        }

        .status-fail {
            color: #b32525;
            font-weight: bold;
        }

        form {
            display: grid;
            gap: 22px;
        }

        .section {
            border: 1px solid #e6ebf4;
            border-radius: 16px;
            padding: 20px;
        }

        .section-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .field {
            display: grid;
            gap: 8px;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        label {
            font-weight: bold;
            color: #22314f;
        }

        input {
            width: 100%;
            border: 1px solid #cfd8e6;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 15px;
        }

        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }

        .button,
        .button-secondary {
            display: inline-block;
            text-decoration: none;
            border: 0;
            border-radius: 12px;
            padding: 14px 18px;
            font-size: 15px;
            cursor: pointer;
        }

        .button {
            background: #1b5cff;
            color: #fff;
            font-weight: bold;
        }

        .button-secondary {
            background: #edf2ff;
            color: #25408f;
        }

        .note {
            color: #5a6477;
            font-size: 14px;
            line-height: 1.5;
        }

        .success-box p:last-child,
        .note:last-child {
            margin-bottom: 0;
        }

        @media (max-width: 760px) {
            .intro,
            .section-grid,
            .check {
                display: block;
            }

            .intro-badge,
            .check span {
                margin-top: 12px;
                display: inline-block;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="card">
            <div class="intro">
                <div>
                    <h1>Instalador KMKZ IPTV</h1>
                    <p>Use esta tela depois de enviar os arquivos para a hospedagem. Ela cria o arquivo <code>.env</code>, importa o banco e configura o administrador inicial.</p>
                </div>
                <div class="intro-badge">Hospedagem compartilhada</div>
            </div>

            <?php if ($locked): ?>
                <div class="alert locked">
                    <strong>Instalacao bloqueada.</strong> O sistema ja possui um arquivo <code>.env</code>. Para reinstalar, acesse <code>?force=1</code>.
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert error">
                    <strong>Erro:</strong> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if (isset($success) && $success): ?>
                <div class="alert success success-box">
                    <strong><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></strong>
                    <p>Administrador criado com o email <code><?= htmlspecialchars($config['admin_email'], ENT_QUOTES, 'UTF-8') ?></code>.</p>
                    <p>Por seguranca, o instalador fica bloqueado apos a criacao do <code>.env</code>. Mantenha o arquivo <code>public/install.php</code> fora de uso ou remova-o apos validar o sistema.</p>
                </div>

                <div class="actions">
                    <a class="button" href="./">Abrir site</a>
                    <a class="button-secondary" href="./login.php">Ir para login</a>
                    <a class="button-secondary" href="./api/">Testar API</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Pre-requisitos</h2>
            <div class="checks">
                <?php foreach ($statusChecks as $check): ?>
                    <div class="check">
                        <div>
                            <strong><?= htmlspecialchars($check['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <small><?= htmlspecialchars($check['detail'], ENT_QUOTES, 'UTF-8') ?></small>
                        </div>
                        <span class="<?= $check['ok'] ? 'status-ok' : 'status-fail' ?>">
                            <?= $check['ok'] ? 'OK' : 'Falhou' ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ((!isset($success) || !$success) && !$locked): ?>
            <div class="card">
                <h2>Configuracao Inicial</h2>
                <?php if (!$statusOk): ?>
                    <p class="note">Se o item "Arquivo SQL" falhar, voce ainda pode instalar se ja importou o banco manualmente. Se o banco estiver vazio, envie a pasta <code>database/</code> para o servidor.</p>
                <?php endif; ?>

                <form method="post">
                    <?php if ($envExists): ?>
                        <p class="note">Se voce ja importou o banco manualmente e as tabelas principais ja existem, o instalador vai apenas criar o admin e gerar o <code>.env</code>. Para reinstalar e recriar toda a estrutura, acesse <code>?force=1</code>.</p>
                    <?php endif; ?>
                    <div class="section">
                        <h3>Banco de Dados</h3>
                        <div class="section-grid">
                            <div class="field">
                                <label for="db_host">Host</label>
                                <input id="db_host" name="db_host" type="text" value="<?= htmlspecialchars($config['db_host'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div class="field">
                                <label for="db_port">Porta</label>
                                <input id="db_port" name="db_port" type="text" value="<?= htmlspecialchars($config['db_port'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div class="field full">
                                <label for="db_name">Nome do banco</label>
                                <input id="db_name" name="db_name" type="text" value="<?= htmlspecialchars($config['db_name'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div class="field">
                                <label for="db_user">Usuario</label>
                                <input id="db_user" name="db_user" type="text" value="<?= htmlspecialchars($config['db_user'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div class="field">
                                <label for="db_pass">Senha</label>
                                <input id="db_pass" name="db_pass" type="password" value="<?= htmlspecialchars($config['db_pass'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="section">
                        <h3>Administrador</h3>
                        <div class="section-grid">
                            <div class="field full">
                                <label for="admin_name">Nome</label>
                                <input id="admin_name" name="admin_name" type="text" value="<?= htmlspecialchars($config['admin_name'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div class="field">
                                <label for="admin_email">Email</label>
                                <input id="admin_email" name="admin_email" type="email" value="<?= htmlspecialchars($config['admin_email'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div class="field">
                                <label for="admin_password">Senha</label>
                                <input id="admin_password" name="admin_password" type="password" value="<?= htmlspecialchars($config['admin_password'], ENT_QUOTES, 'UTF-8') ?>" minlength="6" required>
                            </div>
                        </div>
                    </div>

                    <div class="section">
                        <h3>Aplicacao</h3>
                        <div class="section-grid">
                            <div class="field">
                                <label for="app_name">Nome do site</label>
                                <input id="app_name" name="app_name" type="text" value="<?= htmlspecialchars($config['app_name'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div class="field">
                                <label for="timezone">Timezone</label>
                                <input id="timezone" name="timezone" type="text" value="<?= htmlspecialchars($config['timezone'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div class="field full">
                                <label for="app_url">URL do site</label>
                                <input id="app_url" name="app_url" type="url" value="<?= htmlspecialchars($config['app_url'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div class="field full">
                                <label for="cors_allowed_origins">Origens permitidas do CORS</label>
                                <input id="cors_allowed_origins" name="cors_allowed_origins" type="text" value="<?= htmlspecialchars($config['cors_allowed_origins'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="field">
                                <label for="ga4_measurement_id">GA4 Measurement ID</label>
                                <input id="ga4_measurement_id" name="ga4_measurement_id" type="text" value="<?= htmlspecialchars($config['ga4_measurement_id'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="field">
                                <label for="meta_pixel_id">Meta Pixel ID</label>
                                <input id="meta_pixel_id" name="meta_pixel_id" type="text" value="<?= htmlspecialchars($config['meta_pixel_id'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="actions">
                        <button class="button" type="submit">Instalar sistema</button>
                        <span class="note">Se o usuario do banco nao puder criar databases, crie o banco no painel da hospedagem antes de continuar. Se a pasta <code>database/</code> nao estiver no servidor, importe o SQL manualmente antes de instalar.</span>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
