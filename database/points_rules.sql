-- Tabela para gerenciar regras de pontuação
CREATE TABLE IF NOT EXISTS points_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    action_type ENUM('referral', 'payment', 'subscription', 'login_streak', 'social_share', 'review', 'custom') NOT NULL,
    points_awarded INT NOT NULL DEFAULT 0,
    conditions_json TEXT, -- JSON para condições específicas
    is_active BOOLEAN DEFAULT TRUE,
    max_per_user INT DEFAULT NULL, -- Limite máximo por usuário (NULL = ilimitado)
    max_per_day INT DEFAULT NULL, -- Limite máximo por dia (NULL = ilimitado)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela para histórico de pontos ganhos
CREATE TABLE IF NOT EXISTS points_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    rule_id INT,
    points_earned INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    description TEXT,
    reference_id INT DEFAULT NULL, -- ID de referência (ex: subscription_id, referral_id)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (rule_id) REFERENCES points_rules(id) ON DELETE SET NULL
);

-- Inserir regras padrão de pontuação
INSERT INTO points_rules (name, description, action_type, points_awarded, conditions_json, is_active) VALUES
('Indicação de Amigo', 'Ganhe pontos ao indicar um amigo que se cadastre', 'referral', 100, '{"requires_signup": true}', TRUE),
('Pagamento Realizado', 'Ganhe pontos a cada pagamento realizado', 'payment', 50, '{"min_amount": 10}', TRUE),
('Nova Assinatura', 'Ganhe pontos ao fazer uma nova assinatura', 'subscription', 200, '{"subscription_types": ["monthly", "annual"]}', TRUE),
('Login Diário', 'Ganhe pontos por fazer login diariamente', 'login_streak', 10, '{"consecutive_days": 1}', TRUE),
('Compartilhamento Social', 'Ganhe pontos ao compartilhar nas redes sociais', 'social_share', 25, '{"platforms": ["facebook", "twitter", "whatsapp"]}', TRUE),
('Avaliação do Serviço', 'Ganhe pontos ao avaliar nosso serviço', 'review', 75, '{"min_rating": 4}', TRUE);

-- Índices para melhor performance
CREATE INDEX idx_points_history_user_id ON points_history(user_id);
CREATE INDEX idx_points_history_created_at ON points_history(created_at);
CREATE INDEX idx_points_rules_action_type ON points_rules(action_type);
CREATE INDEX idx_points_rules_is_active ON points_rules(is_active);