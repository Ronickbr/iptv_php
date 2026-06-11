-- Atualização do banco de dados para sistema de indicações com códigos únicos
-- Adiciona tabela de referrals e campo referral_code na tabela users

USE `kmkz_iptv`;

-- --------------------------------------------------------
-- Adicionar campo referral_code na tabela users
-- --------------------------------------------------------

ALTER TABLE `users` ADD COLUMN `referral_code` VARCHAR(6) UNIQUE DEFAULT NULL AFTER `points`;

-- --------------------------------------------------------
-- Estrutura da tabela `referrals`
-- --------------------------------------------------------

CREATE TABLE `referrals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `referrer_id` int(11) NOT NULL,
  `referred_email` varchar(150) NOT NULL,
  `referred_user_id` int(11) DEFAULT NULL,
  `status` enum('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
  `points_awarded` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_referrer_id` (`referrer_id`),
  KEY `idx_referred_user_id` (`referred_user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_referrals_referrer` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_referrals_referred` FOREIGN KEY (`referred_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Função para gerar código de referência único
-- --------------------------------------------------------

DELIMITER //

CREATE FUNCTION generate_referral_code() RETURNS VARCHAR(6)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE code VARCHAR(6);
    DECLARE done INT DEFAULT 0;
    
    REPEAT
        SET code = UPPER(CONCAT(
            SUBSTRING('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', FLOOR(1 + RAND() * 36), 1),
            SUBSTRING('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', FLOOR(1 + RAND() * 36), 1),
            SUBSTRING('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', FLOOR(1 + RAND() * 36), 1),
            SUBSTRING('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', FLOOR(1 + RAND() * 36), 1),
            SUBSTRING('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', FLOOR(1 + RAND() * 36), 1),
            SUBSTRING('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', FLOOR(1 + RAND() * 36), 1)
        ));
        
        SELECT COUNT(*) INTO done FROM users WHERE referral_code = code;
    UNTIL done = 0 END REPEAT;
    
    RETURN code;
END//

DELIMITER ;

-- --------------------------------------------------------
-- Trigger para gerar código de referência automaticamente
-- --------------------------------------------------------

DELIMITER //

CREATE TRIGGER `generate_referral_code_on_insert` 
BEFORE INSERT ON `users` 
FOR EACH ROW 
BEGIN
    IF NEW.referral_code IS NULL THEN
        SET NEW.referral_code = generate_referral_code();
    END IF;
END//

DELIMITER ;

-- --------------------------------------------------------
-- Gerar códigos para usuários existentes
-- --------------------------------------------------------

UPDATE users SET referral_code = generate_referral_code() WHERE referral_code IS NULL;

-- --------------------------------------------------------
-- Trigger para atualizar referrals quando usuário se cadastra
-- --------------------------------------------------------

DELIMITER //

CREATE TRIGGER `update_referral_on_signup` 
AFTER INSERT ON `users` 
FOR EACH ROW 
BEGIN
    DECLARE referrer_id INT;
    
    -- Verificar se existe uma indicação pendente para este email
    SELECT r.referrer_id INTO referrer_id 
    FROM referrals r 
    WHERE r.referred_email = NEW.email 
    AND r.status = 'pending' 
    LIMIT 1;
    
    -- Se encontrou uma indicação pendente, atualizar
    IF referrer_id IS NOT NULL THEN
        UPDATE referrals 
        SET referred_user_id = NEW.id, 
            status = 'completed', 
            completed_at = NOW(),
            points_awarded = 200
        WHERE referrer_id = referrer_id 
        AND referred_email = NEW.email 
        AND status = 'pending';
        
        -- Conceder pontos ao indicador
        INSERT INTO points_history (user_id, rule_id, points, action_type, description, reference_id)
        SELECT referrer_id, pr.id, 200, 'referral', 
               CONCAT('Indicação de ', NEW.name, ' (', NEW.email, ')'), NEW.id
        FROM points_rules pr 
        WHERE pr.action_type = 'referral' AND pr.is_active = 1 
        LIMIT 1;
    END IF;
END//

DELIMITER ;

COMMIT;