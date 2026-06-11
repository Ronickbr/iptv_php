-- Estrutura do Banco de Dados KMKZ IPTV
-- Versão: 1.0
-- Data: 2024

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Criação do banco de dados
CREATE DATABASE IF NOT EXISTS `kmkz_iptv` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `kmkz_iptv`;

-- --------------------------------------------------------
-- Estrutura da tabela `users`
-- --------------------------------------------------------

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `user_type` enum('admin','user') NOT NULL DEFAULT 'user',
  `role` enum('admin','user') NOT NULL DEFAULT 'user' COMMENT 'Alias de user_type para compatibilidade',
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `points` int(11) NOT NULL DEFAULT 0,
  `referral_code` varchar(10) UNIQUE DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `login_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `referral_code` (`referral_code`),
  KEY `idx_user_type` (`user_type`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura da tabela `plans`
-- --------------------------------------------------------

CREATE TABLE `plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `price` decimal(10,2) NOT NULL,
  `duration_months` int(11) NOT NULL DEFAULT 1,
  `features` text,
  `max_devices` int(11) NOT NULL DEFAULT 1,
  `quality` varchar(20) NOT NULL DEFAULT 'HD',
  `is_popular` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_popular` (`is_popular`),
  KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura da tabela `subscriptions`
-- --------------------------------------------------------

CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `status` enum('active','expired','cancelled','pending') NOT NULL DEFAULT 'pending',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `auto_renew` tinyint(1) NOT NULL DEFAULT 1,
  `devices_used` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_plan_id` (`plan_id`),
  KEY `idx_status` (`status`),
  KEY `idx_end_date` (`end_date`),
  CONSTRAINT `fk_subscriptions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_subscriptions_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura da tabela `payments`
-- --------------------------------------------------------

CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subscription_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('credit_card','debit_card','pix','boleto','paypal') NOT NULL,
  `status` enum('pending','completed','failed','cancelled','refunded') NOT NULL DEFAULT 'pending',
  `transaction_id` varchar(100) DEFAULT NULL,
  `gateway_response` text,
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_subscription_id` (`subscription_id`),
  KEY `idx_status` (`status`),
  KEY `idx_payment_method` (`payment_method`),
  KEY `idx_transaction_id` (`transaction_id`),
  KEY `idx_paid_at` (`paid_at`),
  CONSTRAINT `fk_payments_subscription` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura da tabela `points_rules`
-- --------------------------------------------------------

CREATE TABLE `points_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `action_type` varchar(50) NOT NULL,
  `points_awarded` int(11) NOT NULL,
  `max_per_day` int(11) DEFAULT NULL,
  `max_per_user` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura da tabela `points_history`
-- --------------------------------------------------------

CREATE TABLE `points_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `rule_id` int(11) DEFAULT NULL,
  `points` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_rule_id` (`rule_id`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_points_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_points_history_rule` FOREIGN KEY (`rule_id`) REFERENCES `points_rules` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura da tabela `rewards`
-- --------------------------------------------------------

CREATE TABLE `rewards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `points_required` int(11) NOT NULL,
  `reward_type` enum('discount','free_month','upgrade','gift') NOT NULL,
  `reward_value` varchar(100) DEFAULT NULL,
  `max_redemptions` int(11) DEFAULT NULL,
  `current_redemptions` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `valid_until` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_points_required` (`points_required`),
  KEY `idx_reward_type` (`reward_type`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_valid_until` (`valid_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura da tabela `user_rewards`
-- --------------------------------------------------------

CREATE TABLE `user_rewards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `reward_id` int(11) NOT NULL,
  `points_spent` int(11) NOT NULL,
  `status` enum('redeemed','used','expired') NOT NULL DEFAULT 'redeemed',
  `redeemed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `used_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_reward_id` (`reward_id`),
  KEY `idx_status` (`status`),
  KEY `idx_redeemed_at` (`redeemed_at`),
  CONSTRAINT `fk_user_rewards_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_rewards_reward` FOREIGN KEY (`reward_id`) REFERENCES `rewards` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura da tabela `audit_log`
-- --------------------------------------------------------

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` text,
  `new_values` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_table_name` (`table_name`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_audit_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura da tabela `system_settings`
-- --------------------------------------------------------

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_type` enum('string','integer','boolean','json') NOT NULL DEFAULT 'string',
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura da tabela `failed_logins`
-- --------------------------------------------------------

CREATE TABLE `failed_logins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `attempts` int(11) NOT NULL DEFAULT 1,
  `last_attempt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `blocked_until` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip_address` (`ip_address`),
  KEY `idx_last_attempt` (`last_attempt`),
  KEY `idx_blocked_until` (`blocked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura da tabela `password_resets`
-- --------------------------------------------------------

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `requested_ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_token_hash` (`token_hash`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expires_at` (`expires_at`),
  CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Inserção de dados iniciais
-- --------------------------------------------------------

-- Usuário administrador padrão
INSERT INTO `users` (`name`, `email`, `password`, `user_type`, `status`, `points`) VALUES
('Administrador', 'admin@kmkz.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', 1000);
-- Senha: admin123

-- Usuário de teste
INSERT INTO `users` (`name`, `email`, `password`, `user_type`, `status`, `points`) VALUES
('Usuário Teste', 'user@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'active', 500);
-- Senha: admin123

-- Planos iniciais
INSERT INTO `plans` (`name`, `description`, `price`, `duration_months`, `features`, `max_devices`, `quality`, `is_popular`, `sort_order`) VALUES
('Básico', 'Plano básico com qualidade SD e 1 dispositivo', 19.90, 1, 'Qualidade SD, 1 dispositivo, Suporte por email', 1, 'SD', 0, 1),
('Padrão', 'Plano padrão com qualidade HD e 2 dispositivos', 29.90, 1, 'Qualidade HD, 2 dispositivos, Suporte prioritário', 2, 'HD', 1, 2),
('Premium', 'Plano premium com qualidade 4K e 4 dispositivos', 49.90, 1, 'Qualidade 4K, 4 dispositivos, Suporte 24/7, Conteúdo exclusivo', 4, '4K', 0, 3),
('Anual Básico', 'Plano básico anual com desconto', 199.90, 12, 'Qualidade SD, 1 dispositivo, Suporte por email, Desconto anual', 1, 'SD', 0, 4),
('Anual Premium', 'Plano premium anual com desconto', 499.90, 12, 'Qualidade 4K, 4 dispositivos, Suporte 24/7, Conteúdo exclusivo, Desconto anual', 4, '4K', 1, 5);

-- Regras de pontos iniciais
INSERT INTO `points_rules` (`name`, `description`, `action_type`, `points_awarded`, `max_per_day`, `max_per_user`) VALUES
('Login Diário', 'Pontos por fazer login diário', 'daily_login', 10, 1, NULL),
('Cadastro', 'Pontos por se cadastrar na plataforma', 'registration', 100, NULL, 1),
('Compartilhamento Social', 'Pontos por compartilhar nas redes sociais', 'social_share', 25, 3, NULL),
('Avaliação', 'Pontos por avaliar o serviço', 'rating', 50, 1, 1),
('Indicação de Amigo', 'Pontos por indicar um amigo', 'referral', 200, NULL, NULL),
('Renovação de Assinatura', 'Pontos por renovar assinatura', 'subscription_renewal', 150, NULL, NULL),
('Feedback', 'Pontos por enviar feedback', 'feedback', 30, 1, NULL);

-- Recompensas iniciais
INSERT INTO `rewards` (`name`, `description`, `points_required`, `reward_type`, `reward_value`, `max_redemptions`) VALUES
('Desconto 10%', 'Desconto de 10% na próxima mensalidade', 200, 'discount', '10', NULL),
('Desconto 20%', 'Desconto de 20% na próxima mensalidade', 400, 'discount', '20', NULL),
('Mês Grátis', 'Um mês grátis de assinatura', 800, 'free_month', '1', NULL),
('Upgrade Temporário', 'Upgrade para plano superior por 1 mês', 600, 'upgrade', '1', NULL),
('Desconto 50%', 'Desconto especial de 50%', 1000, 'discount', '50', 10),
('Brinde Exclusivo', 'Brinde exclusivo KMKZ', 300, 'gift', 'Camiseta KMKZ', 50);

-- Configurações do sistema
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('site_name', 'KMKZ IPTV', 'string', 'Nome do site'),
('site_url', 'https://kmkz.com', 'string', 'URL do site'),
('admin_email', 'admin@kmkz.com', 'string', 'Email do administrador'),
('max_login_attempts', '5', 'integer', 'Máximo de tentativas de login'),
('session_timeout', '3600', 'integer', 'Timeout da sessão em segundos'),
('points_enabled', '1', 'boolean', 'Sistema de pontos habilitado'),
('maintenance_mode', '0', 'boolean', 'Modo de manutenção'),
('api_version', '1.0', 'string', 'Versão da API'),
('default_plan_duration', '1', 'integer', 'Duração padrão do plano em meses'),
('currency', 'BRL', 'string', 'Moeda padrão');

COMMIT;

-- --------------------------------------------------------
-- Triggers para atualização automática de pontos
-- --------------------------------------------------------

DELIMITER //

CREATE TRIGGER `update_user_points_after_insert` 
AFTER INSERT ON `points_history` 
FOR EACH ROW 
BEGIN
    UPDATE users 
    SET points = points + NEW.points 
    WHERE id = NEW.user_id;
END//

CREATE TRIGGER `update_user_points_after_delete` 
AFTER DELETE ON `points_history` 
FOR EACH ROW 
BEGIN
    UPDATE users 
    SET points = points - OLD.points 
    WHERE id = OLD.user_id;
END//

CREATE TRIGGER `update_reward_redemptions` 
AFTER INSERT ON `user_rewards` 
FOR EACH ROW 
BEGIN
    UPDATE rewards 
    SET current_redemptions = current_redemptions + 1 
    WHERE id = NEW.reward_id;
END//

DELIMITER ;

-- --------------------------------------------------------
-- Views úteis
-- --------------------------------------------------------

-- View para estatísticas de usuários
CREATE VIEW `user_stats` AS
SELECT 
    u.id,
    u.name,
    u.email,
    u.user_type,
    u.status,
    u.points,
    u.login_count,
    u.last_login,
    u.created_at,
    COUNT(DISTINCT s.id) as total_subscriptions,
    COUNT(DISTINCT CASE WHEN s.status = 'active' THEN s.id END) as active_subscriptions,
    SUM(DISTINCT p.amount) as total_spent,
    COUNT(DISTINCT ur.id) as rewards_redeemed
FROM users u
LEFT JOIN subscriptions s ON u.id = s.user_id
LEFT JOIN payments p ON s.id = p.subscription_id AND p.status = 'completed'
LEFT JOIN user_rewards ur ON u.id = ur.user_id
GROUP BY u.id;

-- View para estatísticas de planos
CREATE VIEW `plan_stats` AS
SELECT 
    p.id,
    p.name,
    p.price,
    p.duration_months,
    p.status,
    p.is_popular,
    COUNT(DISTINCT s.id) as total_subscriptions,
    COUNT(DISTINCT CASE WHEN s.status = 'active' THEN s.id END) as active_subscriptions,
    SUM(DISTINCT pay.amount) as total_revenue,
    AVG(DISTINCT pay.amount) as avg_revenue
FROM plans p
LEFT JOIN subscriptions s ON p.id = s.plan_id
LEFT JOIN payments pay ON s.id = pay.subscription_id AND pay.status = 'completed'
GROUP BY p.id;

-- Índices adicionais para performance
CREATE INDEX idx_users_email_status ON users(email, status);
CREATE INDEX idx_subscriptions_user_status ON subscriptions(user_id, status);
CREATE INDEX idx_payments_status_amount ON payments(status, amount);
CREATE INDEX idx_points_history_user_created ON points_history(user_id, created_at);
CREATE INDEX idx_user_rewards_user_status ON user_rewards(user_id, status);

-- Fim do arquivo SQL
