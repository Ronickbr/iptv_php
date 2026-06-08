<?php
/**
 * Script de Migração do Banco de Dados - KMKZ IPTV
 * 
 * ATENÇÃO: Executar apenas UMA VEZ para corrigir a estrutura do banco.
 * Após executar com sucesso, este arquivo pode ser deletado por segurança.
 * 
 * Acesse via navegador: http://seu-dominio/migrate.php
 */

// Proteção básica — altere ou remova após uso em produção
define('MIGRATE_TOKEN', 'kmkz-migrate-2024');

$appEnv = strtolower(getenv('APP_ENV') ?: 'production');
$allowMigrations = filter_var(getenv('ALLOW_MIGRATIONS') ?: '0', FILTER_VALIDATE_BOOLEAN);
if (!$allowMigrations && $appEnv === 'production') {
    http_response_code(404);
    exit;
}

if (!isset($_GET['token']) || $_GET['token'] !== MIGRATE_TOKEN) {
    http_response_code(403);
    die('Acesso negado. Informe o token correto.');
}

// --- Executar migração ---

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$results = [];
$hasError = false;

try {
    $db = getDB();

    // =========================================================
    // 1. Adicionar coluna referral_code na tabela users
    // =========================================================
    try {
        $db->exec("ALTER TABLE users ADD COLUMN referral_code VARCHAR(10) UNIQUE DEFAULT NULL AFTER points");
        $results[] = ['ok', 'Coluna <strong>referral_code</strong> adicionada na tabela users.'];
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false || strpos($e->getMessage(), 'already exists') !== false) {
            $results[] = ['warn', 'Coluna <strong>referral_code</strong> já existe (ignorado).'];
        } else {
            throw $e;
        }
    }

    // =========================================================
    // 2. Adicionar coluna role na tabela users (alias de user_type)
    // =========================================================
    // Verifica se a coluna 'role' existe, pois alguns arquivos usam $user['role']
    try {
        $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'role'");
        if ($stmt->rowCount() === 0) {
            // Adiciona coluna virtual / computed ou real dependendo do suporte
            $db->exec("ALTER TABLE users ADD COLUMN role ENUM('admin','user') NOT NULL DEFAULT 'user' AFTER user_type");
            $results[] = ['ok', 'Coluna <strong>role</strong> adicionada na tabela users.'];
            // Sincroniza com user_type
            $db->exec("UPDATE users SET role = user_type");
            $results[] = ['ok', 'Dados de <strong>role</strong> sincronizados com user_type.'];
        } else {
            $results[] = ['warn', 'Coluna <strong>role</strong> já existe (ignorado).'];
        }
    } catch (PDOException $e) {
        $results[] = ['warn', 'Coluna role: ' . $e->getMessage()];
    }

    // =========================================================
    // 3. Criar tabela referrals (se não existir)
    // =========================================================
    try {
        $sql = "
        CREATE TABLE IF NOT EXISTS referrals (
            id int(11) NOT NULL AUTO_INCREMENT,
            referrer_id int(11) NOT NULL,
            referred_email varchar(150) NOT NULL,
            referred_user_id int(11) DEFAULT NULL,
            status enum('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
            points_awarded int(11) DEFAULT 0,
            created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at timestamp NULL DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_referrer_id (referrer_id),
            KEY idx_referred_user_id (referred_user_id),
            KEY idx_status (status),
            KEY idx_created_at (created_at),
            CONSTRAINT fk_referrals_referrer FOREIGN KEY (referrer_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT fk_referrals_referred FOREIGN KEY (referred_user_id) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $db->exec($sql);
        $results[] = ['ok', 'Tabela <strong>referrals</strong> criada ou já existia.'];
    } catch (PDOException $e) {
        $results[] = ['error', 'Erro ao criar tabela referrals: ' . $e->getMessage()];
        $hasError = true;
    }

    // =========================================================
    // 4. Gerar códigos de referência para usuários sem código
    // =========================================================
    try {
        $stmt = $db->prepare("SELECT id FROM users WHERE referral_code IS NULL OR referral_code = ''");
        $stmt->execute();
        $usersWithoutCode = $stmt->fetchAll();

        $updated = 0;
        foreach ($usersWithoutCode as $u) {
            $code = generateReferralCode($db);
            if ($code) {
                $upd = $db->prepare("UPDATE users SET referral_code = ? WHERE id = ?");
                $upd->execute([$code, $u['id']]);
                $updated++;
            }
        }
        $results[] = ['ok', "<strong>{$updated}</strong> código(s) de referência gerado(s) para usuários existentes."];
    } catch (PDOException $e) {
        $results[] = ['error', 'Erro ao gerar códigos: ' . $e->getMessage()];
        $hasError = true;
    }

    // =========================================================
    // 5. Verificar coluna conditions_json em points_rules
    // =========================================================
    try {
        $stmt = $db->query("SHOW COLUMNS FROM points_rules LIKE 'conditions_json'");
        if ($stmt->rowCount() === 0) {
            $db->exec("ALTER TABLE points_rules ADD COLUMN conditions_json TEXT NULL DEFAULT NULL AFTER max_per_user");
            $results[] = ['ok', 'Coluna <strong>conditions_json</strong> adicionada na tabela points_rules.'];
        } else {
            $results[] = ['warn', 'Coluna <strong>conditions_json</strong> já existe em points_rules (ignorado).'];
        }
    } catch (PDOException $e) {
        $results[] = ['warn', 'conditions_json: ' . $e->getMessage()];
    }

    // =========================================================
    // 6. Verificar coluna points_earned em points_history
    //    (o código usa points_earned mas o schema define 'points')
    // =========================================================
    try {
        $stmt = $db->query("SHOW COLUMNS FROM points_history LIKE 'points_earned'");
        if ($stmt->rowCount() === 0) {
            $db->exec("ALTER TABLE points_history ADD COLUMN points_earned INT(11) NOT NULL DEFAULT 0 AFTER points");
            // Sincroniza com a coluna 'points'
            $db->exec("UPDATE points_history SET points_earned = points WHERE points_earned = 0");
            $results[] = ['ok', 'Coluna <strong>points_earned</strong> adicionada e sincronizada na tabela points_history.'];
        } else {
            $results[] = ['warn', 'Coluna <strong>points_earned</strong> já existe em points_history (ignorado).'];
        }
    } catch (PDOException $e) {
        $results[] = ['warn', 'points_earned: ' . $e->getMessage()];
    }

} catch (PDOException $e) {
    $results[] = ['error', '❌ Erro crítico: ' . $e->getMessage()];
    $hasError = true;
}

// =========================================================
// Exibir resultado
// =========================================================
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Migração - KMKZ IPTV</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 700px; margin: 40px auto; padding: 20px; background: #111; color: #eee; }
        h1 { color: #f90; }
        h2 { color: <?= $hasError ? '#ff4444' : '#44dd88' ?>; }
        .item { padding: 10px 14px; border-radius: 6px; margin: 6px 0; font-size: 0.95rem; }
        .ok   { background: #1a3a1a; border-left: 4px solid #4caf50; }
        .warn { background: #2a2a0a; border-left: 4px solid #ffc107; }
        .error{ background: #3a1a1a; border-left: 4px solid #f44336; }
        .icon { margin-right: 8px; }
        a.btn { display: inline-block; margin-top: 24px; padding: 12px 28px; background: #1565c0; color: #fff; border-radius: 6px; text-decoration: none; font-weight: bold; }
        a.btn:hover { background: #1976d2; }
        .warning-box { background: #3a2a0a; border: 1px solid #f90; border-radius: 6px; padding: 14px 18px; margin-top: 20px; }
    </style>
</head>
<body>
    <h1>🛠️ Migração do Banco de Dados - KMKZ IPTV</h1>
    <h2><?= $hasError ? '❌ Migração concluída com erros' : '✅ Migração concluída com sucesso!' ?></h2>

    <?php foreach ($results as [$type, $msg]): ?>
        <div class="item <?= $type ?>">
            <span class="icon"><?= $type === 'ok' ? '✓' : ($type === 'warn' ? '⚠' : '✗') ?></span>
            <?= $msg ?>
        </div>
    <?php endforeach; ?>

    <?php if (!$hasError): ?>
        <div class="warning-box">
            <strong>⚠️ Segurança:</strong> Após confirmar que o dashboard está funcionando, 
            delete este arquivo <code>migrate.php</code> do servidor para evitar re-execuções acidentais.
        </div>
        <a class="btn" href="dashboard.php">▶ Ir para o Dashboard</a>
    <?php else: ?>
        <p>Verifique os erros acima e consulte o log do servidor para mais detalhes.</p>
    <?php endif; ?>
</body>
</html>
