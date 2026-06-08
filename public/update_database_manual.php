<?php
/**
 * Script para atualizar o banco de dados manualmente
 * Adiciona campo referral_code na tabela users e cria tabela referrals
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$appEnv = strtolower(getenv('APP_ENV') ?: 'production');
$allowMigrations = filter_var(getenv('ALLOW_MIGRATIONS') ?: '0', FILTER_VALIDATE_BOOLEAN);
if (!$allowMigrations && $appEnv === 'production') {
    http_response_code(404);
    exit;
}

try {
    $db = getDB();
    
    echo "<h2>Atualizando estrutura do banco de dados...</h2>";
    
    // 1. Adicionar campo referral_code na tabela users
    echo "<p>1. Adicionando campo referral_code na tabela users...</p>";
    try {
        $db->exec("ALTER TABLE users ADD COLUMN referral_code VARCHAR(6) UNIQUE DEFAULT NULL AFTER points");
        echo "<span style='color: green;'>✓ Campo referral_code adicionado com sucesso!</span><br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "<span style='color: orange;'>⚠ Campo referral_code já existe.</span><br>";
        } else {
            throw $e;
        }
    }
    
    // 2. Criar tabela referrals
    echo "<p>2. Criando tabela referrals...</p>";
    try {
        $sql = "
        CREATE TABLE referrals (
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
        echo "<span style='color: green;'>✓ Tabela referrals criada com sucesso!</span><br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "<span style='color: orange;'>⚠ Tabela referrals já existe.</span><br>";
        } else {
            throw $e;
        }
    }
    
    // 3. Gerar códigos de referência para usuários existentes
    echo "<p>3. Gerando códigos de referência para usuários existentes...</p>";
    $stmt = $db->prepare("SELECT id FROM users WHERE referral_code IS NULL");
    $stmt->execute();
    $usersWithoutCode = $stmt->fetchAll();
    
    $updated = 0;
    foreach ($usersWithoutCode as $user) {
        $referralCode = generateReferralCode($db);
        if ($referralCode) {
            $updateStmt = $db->prepare("UPDATE users SET referral_code = ? WHERE id = ?");
            $updateStmt->execute([$referralCode, $user['id']]);
            $updated++;
        }
    }
    
    echo "<span style='color: green;'>✓ {$updated} códigos de referência gerados!</span><br>";
    
    echo "<h3 style='color: green;'>✅ Atualização concluída com sucesso!</h3>";
    echo "<p><strong>Recursos implementados:</strong></p>";
    echo "<ul>";
    echo "<li>✓ Campo referral_code adicionado na tabela users</li>";
    echo "<li>✓ Tabela referrals criada para gerenciar indicações</li>";
    echo "<li>✓ Códigos únicos de 6 caracteres alfanuméricos gerados</li>";
    echo "<li>✓ Sistema de indicações com códigos únicos implementado</li>";
    echo "</ul>";
    
    // Mostrar alguns códigos gerados
    echo "<p><strong>Exemplos de códigos gerados:</strong></p>";
    $stmt = $db->prepare("SELECT name, email, referral_code FROM users WHERE referral_code IS NOT NULL LIMIT 5");
    $stmt->execute();
    $examples = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th style='padding: 8px;'>Nome</th><th style='padding: 8px;'>Email</th><th style='padding: 8px;'>Código</th></tr>";
    foreach ($examples as $example) {
        echo "<tr>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($example['name']) . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($example['email']) . "</td>";
        echo "<td style='padding: 8px; font-weight: bold; color: blue;'>" . htmlspecialchars($example['referral_code']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (PDOException $e) {
    echo "<h3 style='color: red;'>❌ Erro na atualização:</h3>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Erro geral:</h3>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 20px auto;
    padding: 20px;
    background-color: #f5f5f5;
}
h2, h3 {
    color: #333;
}
p {
    margin: 10px 0;
}
ul {
    margin: 10px 0;
    padding-left: 20px;
}
li {
    margin: 5px 0;
}
</style>
