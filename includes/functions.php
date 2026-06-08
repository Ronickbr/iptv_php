<?php

// Função para buscar todos os planos
function getPlans($db) {
    try {
        $stmt = $db->prepare("SELECT * FROM plans ORDER BY duration_months ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        return [];
    }
}

function ensureMarketingPlans($db) {
    if (!$db) {
        return false;
    }

    try {
        if (!tableExists($db, 'plans')) {
            return false;
        }

        $desired = [
            'Mensal' => [
                'months' => 1,
                'price' => 35.00,
                'description' => 'Para começar hoje, sem compromisso.',
                'sort_order' => 10,
                'is_popular' => 0
            ],
            'Trimestral' => [
                'months' => 3,
                'price' => 99.00,
                'description' => 'Mais tempo para curtir, com economia.',
                'sort_order' => 20,
                'is_popular' => 0
            ],
            'Semestral' => [
                'months' => 6,
                'price' => 180.00,
                'description' => 'Melhor custo-benefício para a maioria.',
                'sort_order' => 30,
                'is_popular' => 1
            ],
            'Anual' => [
                'months' => 12,
                'price' => 300.00,
                'description' => 'Máxima economia para quem já decidiu.',
                'sort_order' => 40,
                'is_popular' => 0
            ]
        ];

        $stmt = $db->prepare("SELECT * FROM plans WHERE name IN ('Mensal','Trimestral','Semestral','Anual') LIMIT 4");
        $stmt->execute();
        $existingRows = $stmt->fetchAll();
        $existingByName = [];
        foreach ($existingRows as $row) {
            if (!empty($row['name'])) {
                $existingByName[$row['name']] = $row;
            }
        }

        $stmt = $db->prepare("
            SELECT *
            FROM plans
            WHERE status = 'active'
            ORDER BY is_popular DESC, sort_order ASC, duration_months ASC, price ASC, id ASC
            LIMIT 1
        ");
        $stmt->execute();
        $basePlan = $stmt->fetch();

        $features = $basePlan['features'] ?? null;
        $maxDevices = isset($basePlan['max_devices']) ? (int)$basePlan['max_devices'] : 1;
        $quality = $basePlan['quality'] ?? 'HD';

        foreach ($desired as $name => $config) {
            $months = (int)$config['months'];
            $price = (float)$config['price'];
            $isPopular = (int)$config['is_popular'];
            $sortOrder = (int)$config['sort_order'];
            $defaultDescription = (string)$config['description'];

            if (isset($existingByName[$name])) {
                $stmt = $db->prepare("
                    UPDATE plans
                    SET price = ?,
                        duration_months = ?,
                        is_popular = ?,
                        status = 'active',
                        sort_order = ?,
                        description = CASE
                            WHEN description IS NULL OR description = '' THEN ?
                            ELSE description
                        END
                    WHERE id = ?
                ");
                $stmt->execute([
                    $price,
                    $months,
                    $isPopular,
                    $sortOrder,
                    $defaultDescription,
                    $existingByName[$name]['id']
                ]);
                continue;
            }

            $stmt = $db->prepare("
                INSERT INTO plans (name, description, price, duration_months, features, max_devices, quality, is_popular, status, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)
            ");
            $stmt->execute([
                $name,
                $defaultDescription,
                $price,
                $months,
                $features,
                $maxDevices,
                $quality,
                $isPopular,
                $sortOrder
            ]);
        }

        return true;
    } catch(PDOException $e) {
        return false;
    }
}

// Função para buscar todos os planos (alias para compatibilidade)
function getAllPlans($db) {
    return getPlans($db);
}

// Função para buscar um plano específico
function getPlan($db, $id) {
    try {
        $stmt = $db->prepare("SELECT * FROM plans WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch(PDOException $e) {
        return null;
    }
}

// Função para buscar depoimentos em destaque
function getFeaturedTestimonials($db) {
    try {
        $stmt = $db->prepare("SELECT * FROM testimonials WHERE is_featured = 1 ORDER BY created_at DESC LIMIT 3");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        return [];
    }
}

// Função para buscar todos os depoimentos
function getAllTestimonials($db) {
    try {
        $stmt = $db->prepare("SELECT * FROM testimonials ORDER BY created_at DESC");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        return [];
    }
}

// Função para criar um novo usuário
function createUser($db, $name, $email, $password, $phone = null) {
    try {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $hasReferralCodeColumn = columnExists($db, 'users', 'referral_code');
        if ($hasReferralCodeColumn) {
            $referralCode = generateReferralCode($db);
            $stmt = $db->prepare("INSERT INTO users (name, email, password, phone, referral_code) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $hashedPassword, $phone, $referralCode]);
        } else {
            $stmt = $db->prepare("INSERT INTO users (name, email, password, phone) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $hashedPassword, $phone]);
        }
        $userId = $db->lastInsertId();
        
        // Processar indicação se existir
        if ($userId) {
            processReferralSignup($db, $userId, $email);
        }
        
        return $userId;
    } catch(PDOException $e) {
        return false;
    }
}

// Função para verificar se email já existe
function emailExists($db, $email) {
    try {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch() !== false;
    } catch(PDOException $e) {
        return false;
    }
}

// Função para criar uma assinatura
function createSubscription($db, $userId, $planId, $paymentMethod = 'pix') {
    try {
        $plan = getPlan($db, $planId);
        if (!$plan) return false;
        
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime("+{$plan['duration_months']} months"));

        $hasPaymentMethodColumn = columnExists($db, 'subscriptions', 'payment_method');
        if ($hasPaymentMethodColumn) {
            $stmt = $db->prepare("INSERT INTO subscriptions (user_id, plan_id, start_date, end_date, payment_method) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $planId, $startDate, $endDate, $paymentMethod]);
        } else {
            $stmt = $db->prepare("INSERT INTO subscriptions (user_id, plan_id, start_date, end_date) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $planId, $startDate, $endDate]);
        }
        $subscriptionId = $db->lastInsertId();
        
        // Conceder pontos por assinatura
        if ($subscriptionId) {
            awardPointsByRule($db, $userId, 'subscription', $subscriptionId, [
                'plan_id' => $planId,
                'plan_price' => $plan['price'],
                'duration_months' => $plan['duration_months']
            ]);
        }
        
        return $subscriptionId;
    } catch(PDOException $e) {
        return false;
    }
}

// Função para buscar usuário por email
function getUserByEmail($db, $email) {
    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch();
    } catch(PDOException $e) {
        return null;
    }
}

// Função para verificar login
function verifyLogin($db, $email, $password) {
    $user = getUserByEmail($db, $email);
    if ($user && password_verify($password, $user['password'])) {
        return $user;
    }
    return false;
}

// Função para adicionar pontos ao usuário
function addPoints($db, $userId, $points) {
    try {
        $stmt = $db->prepare("UPDATE users SET points = points + ? WHERE id = ?");
        $stmt->execute([$points, $userId]);
        return true;
    } catch(PDOException $e) {
        return false;
    }
}

function tableExists($db, $tableName) {
    try {
        $stmt = $db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$tableName]);
        return $stmt->fetchColumn() !== false;
    } catch(PDOException $e) {
        return false;
    }
}

function columnExists($db, $tableName, $columnName) {
    try {
        $stmt = $db->prepare("SHOW COLUMNS FROM `{$tableName}` LIKE ?");
        $stmt->execute([$columnName]);
        return $stmt->fetch() !== false;
    } catch(PDOException $e) {
        return false;
    }
}

function getPointsHistoryPointsColumn($db) {
    return columnExists($db, 'points_history', 'points_earned') ? 'points_earned' : 'points';
}

function insertPointsHistory($db, $userId, $ruleId, $points, $actionType, $description, $referenceId = null) {
    try {
        $pointsCol = getPointsHistoryPointsColumn($db);
        $stmt = $db->prepare("
            INSERT INTO points_history (user_id, rule_id, {$pointsCol}, action_type, description, reference_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $ruleId,
            (int)$points,
            $actionType,
            $description,
            $referenceId
        ]);
        return true;
    } catch(PDOException $e) {
        return false;
    }
}

function syncUserPointsBalance($db, $userId, $daysValid = 90) {
    try {
        $daysValid = max(1, (int)$daysValid);
        $pointsCol = getPointsHistoryPointsColumn($db);
        $sql = "
            SELECT COALESCE(SUM({$pointsCol}), 0) as balance
            FROM points_history
            WHERE user_id = ?
              AND created_at >= DATE_SUB(NOW(), INTERVAL {$daysValid} DAY)
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$userId]);
        $balance = (int)$stmt->fetchColumn();
        if ($balance < 0) {
            $balance = 0;
        }

        $stmt = $db->prepare("UPDATE users SET points = ? WHERE id = ?");
        $stmt->execute([$balance, $userId]);

        return $balance;
    } catch(PDOException $e) {
        return null;
    }
}

function getVipInfo($db, $userId) {
    $vip = [
        'level' => 'bronze',
        'multiplier' => 1.0
    ];

    try {
        $stmt = $db->prepare("SELECT created_at FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) {
            return $vip;
        }

        $stmt = $db->prepare("
            SELECT s.end_date, p.duration_months
            FROM subscriptions s
            JOIN plans p ON s.plan_id = p.id
            WHERE s.user_id = ?
            ORDER BY s.end_date DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $latestSubscription = $stmt->fetch();

        if ($latestSubscription && !empty($latestSubscription['end_date'])) {
            $endTs = strtotime($latestSubscription['end_date']);
            if ($endTs !== false) {
                $graceTs = strtotime('-5 days');
                if ($endTs < $graceTs) {
                    return $vip;
                }
            }
        }

        $createdTs = strtotime($user['created_at']);
        $monthsActive = 0;
        if ($createdTs !== false) {
            $monthsActive = (int)floor((time() - $createdTs) / (30 * 24 * 60 * 60));
        }

        $durationMonths = (int)($latestSubscription['duration_months'] ?? 0);

        if ($durationMonths >= 12 || $monthsActive >= 9) {
            $vip['level'] = 'ouro';
            $vip['multiplier'] = 1.5;
            return $vip;
        }

        if ($durationMonths >= 6 || ($monthsActive >= 4 && $monthsActive <= 8)) {
            $vip['level'] = 'prata';
            $vip['multiplier'] = 1.2;
            return $vip;
        }

        return $vip;
    } catch(PDOException $e) {
        return $vip;
    }
}

// Função para gerar código de referência único
function generateReferralCode($db) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $maxAttempts = 100;
    $attempts = 0;
    
    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        // Verificar se o código já existe
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE referral_code = ?");
            $stmt->execute([$code]);
            $exists = $stmt->fetchColumn() > 0;
        } catch(PDOException $e) {
            $exists = true; // Em caso de erro, gerar outro código
        }
        
        $attempts++;
    } while ($exists && $attempts < $maxAttempts);
    
    return $attempts < $maxAttempts ? $code : null;
}

// Função para buscar usuário por código de referência
function getUserByReferralCode($db, $referralCode) {
    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE referral_code = ?");
        $stmt->execute([$referralCode]);
        return $stmt->fetch();
    } catch(PDOException $e) {
        return null;
    }
}

// Função para criar indicação
function createReferral($db, $referrerId, $referredEmail) {
    try {
        // Verificar se já existe uma indicação para este email
        $stmt = $db->prepare("SELECT id FROM referrals WHERE referrer_id = ? AND referred_email = ?");
        $stmt->execute([$referrerId, $referredEmail]);
        if ($stmt->fetch()) {
            return false; // Já existe indicação
        }
        
        $stmt = $db->prepare("INSERT INTO referrals (referrer_id, referred_email, status) VALUES (?, ?, 'pending')");
        $stmt->execute([$referrerId, $referredEmail]);
        $referralId = $db->lastInsertId();
        
        return $referralId;
    } catch(PDOException $e) {
        return false;
    }
}

// Função para processar indicação quando usuário se cadastra
function processReferralSignup($db, $newUserId, $email) {
    try {
        // Buscar indicação pendente para este email
        $stmt = $db->prepare("SELECT * FROM referrals WHERE referred_email = ? AND status = 'pending'");
        $stmt->execute([$email]);
        $referral = $stmt->fetch();
        
        if ($referral) {
            // Atualizar indicação
            $stmt = $db->prepare("UPDATE referrals SET referred_user_id = ?, status = 'completed', completed_at = NOW(), points_awarded = 0 WHERE id = ?");
            $stmt->execute([$newUserId, $referral['id']]);
            
            return true;
        }
        
        return false;
    } catch(PDOException $e) {
        return false;
    }
}

// Função para contar indicações de um usuário
function getUserReferralsCount($db, $userId) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM referrals WHERE referrer_id = ? AND status = 'completed' AND points_awarded > 0");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    } catch(PDOException $e) {
        return 0;
    }
}

// Função para buscar prêmios disponíveis
function getRewards($db) {
    try {
        $stmt = $db->prepare("
            SELECT *
            FROM rewards
            WHERE is_active = 1
              AND (valid_until IS NULL OR valid_until >= CURDATE())
              AND (max_redemptions IS NULL OR current_redemptions < max_redemptions)
            ORDER BY points_required ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        return [];
    }
}

// Função para resgatar prêmio
function redeemReward($db, $userId, $rewardId) {
    $result = redeemRewardWithResult($db, $userId, $rewardId);
    return (bool)($result['success'] ?? false);
}

function redeemRewardWithResult($db, $userId, $rewardId) {
    try {
        syncUserPointsBalance($db, $userId, 90);
        $db->beginTransaction();

        $user = getUserById($db, $userId);
        $reward = getReward($db, $rewardId);
        if (!$user || !$reward) {
            $db->rollBack();
            return ['success' => false, 'message' => 'Recompensa inválida.'];
        }

        $pointsRequired = (int)($reward['points_required'] ?? 0);
        if ($pointsRequired <= 0 || ((int)$user['points']) < $pointsRequired) {
            $db->rollBack();
            return ['success' => false, 'message' => 'Pontos insuficientes para resgatar este prêmio.'];
        }

        if (!empty($reward['max_redemptions']) && !empty($reward['current_redemptions'])) {
            if ((int)$reward['current_redemptions'] >= (int)$reward['max_redemptions']) {
                $db->rollBack();
                return ['success' => false, 'message' => 'Este prêmio esgotou.'];
            }
        }

        $userRewardId = null;
        if (tableExists($db, 'user_rewards')) {
            $expiresAt = null;
            $status = 'redeemed';
            $usedAt = null;

            if (($reward['reward_type'] ?? '') === 'upgrade') {
                $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
            }

            $stmt = $db->prepare("
                INSERT INTO user_rewards (user_id, reward_id, points_spent, status, redeemed_at, used_at, expires_at)
                VALUES (?, ?, ?, ?, NOW(), ?, ?)
            ");
            $stmt->execute([$userId, $rewardId, $pointsRequired, $status, $usedAt, $expiresAt]);
            $userRewardId = (int)$db->lastInsertId();
        } elseif (tableExists($db, 'reward_redemptions')) {
            $stmt = $db->prepare("INSERT INTO reward_redemptions (user_id, reward_id, points_used) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $rewardId, $pointsRequired]);
            $userRewardId = (int)$db->lastInsertId();
        } else {
            $db->rollBack();
            return ['success' => false, 'message' => 'Tabela de resgates não encontrada no banco.'];
        }

        $stmt = $db->prepare("UPDATE users SET points = points - ? WHERE id = ?");
        $stmt->execute([$pointsRequired, $userId]);

        insertPointsHistory($db, $userId, null, -$pointsRequired, 'reward_redeem', 'Resgate: ' . ($reward['name'] ?? 'Recompensa'), $rewardId);

        $effect = applyRewardEffect($db, $userId, $reward, $userRewardId);
        if (!($effect['success'] ?? false)) {
            $db->rollBack();
            return $effect;
        }

        if (columnExists($db, 'rewards', 'current_redemptions')) {
            $stmt = $db->prepare("UPDATE rewards SET current_redemptions = current_redemptions + 1 WHERE id = ?");
            $stmt->execute([$rewardId]);
        }

        $db->commit();
        syncUserPointsBalance($db, $userId, 90);
        return $effect;
    } catch(PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['success' => false, 'message' => 'Erro ao resgatar prêmio.'];
    }
}

function applyRewardEffect($db, $userId, $reward, $userRewardId = null) {
    $rewardType = (string)($reward['reward_type'] ?? '');
    $rewardValue = (string)($reward['reward_value'] ?? '');

    if ($rewardType === 'free_month') {
        $months = (int)$rewardValue;
        if ($months <= 0) {
            $months = 1;
        }

        if (tableExists($db, 'user_rewards')) {
            $stmt = $db->prepare("
                SELECT COUNT(*)
                FROM user_rewards ur
                JOIN rewards r ON r.id = ur.reward_id
                WHERE ur.user_id = ?
                  AND r.reward_type = 'free_month'
                  AND ur.redeemed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                  AND (? IS NULL OR ur.id <> ?)
            ");
            $stmt->execute([$userId, $userRewardId, $userRewardId]);
            if ((int)$stmt->fetchColumn() >= 1) {
                return ['success' => false, 'message' => 'Você só pode resgatar 1 mês grátis a cada 30 dias.'];
            }
        }

        $applied = applyFreeMonthsToSubscription($db, $userId, $months);
        if (!$applied['success']) {
            return $applied;
        }

        if (tableExists($db, 'user_rewards') && $userRewardId) {
            $stmt = $db->prepare("UPDATE user_rewards SET status = 'used', used_at = NOW() WHERE id = ?");
            $stmt->execute([(int)$userRewardId]);
        }

        return ['success' => true, 'message' => $applied['message']];
    }

    if ($rewardType === 'upgrade') {
        $extraDays = 30;
        $extraCount = 1;

        if (strpos($rewardValue, 'extra_screen:') === 0) {
            $extraDays = (int)substr($rewardValue, strlen('extra_screen:'));
            if ($extraDays <= 0) {
                $extraDays = 30;
            }
        } elseif (!empty($rewardValue) && ctype_digit($rewardValue)) {
            $extraDays = (int)$rewardValue;
        }

        $activeExtras = getActiveExtraScreensCount($db, $userId, $userRewardId);
        if (($activeExtras + $extraCount) > 2) {
            return ['success' => false, 'message' => 'Limite de 2 telas extras por conta atingido.'];
        }

        if (tableExists($db, 'user_rewards') && $userRewardId) {
            $expiresAt = date('Y-m-d H:i:s', strtotime('+' . $extraDays . ' days'));
            $stmt = $db->prepare("UPDATE user_rewards SET expires_at = ?, status = 'redeemed' WHERE id = ?");
            $stmt->execute([$expiresAt, (int)$userRewardId]);
            return ['success' => true, 'message' => 'Tela extra ativada por ' . $extraDays . ' dias.'];
        }

        return ['success' => true, 'message' => 'Resgate concluído.'];
    }

    return ['success' => true, 'message' => 'Resgate concluído.'];
}

function applyFreeMonthsToSubscription($db, $userId, $months) {
    try {
        $months = max(1, (int)$months);
        $stmt = $db->prepare("
            SELECT * FROM subscriptions
            WHERE user_id = ? AND status = 'active'
            ORDER BY end_date DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $subscription = $stmt->fetch();

        $today = new DateTime('today');
        if ($subscription) {
            $endDate = new DateTime($subscription['end_date']);
            $base = $endDate > $today ? $endDate : $today;
            $base->modify('+' . $months . ' months');
            $newEnd = $base->format('Y-m-d');

            $stmt = $db->prepare("UPDATE subscriptions SET end_date = ? WHERE id = ?");
            $stmt->execute([$newEnd, (int)$subscription['id']]);
            return ['success' => true, 'message' => 'Assinatura estendida até ' . date('d/m/Y', strtotime($newEnd)) . '.'];
        }

        $stmt = $db->prepare("SELECT id FROM plans WHERE duration_months = 1 ORDER BY price ASC LIMIT 1");
        $stmt->execute();
        $planId = (int)$stmt->fetchColumn();
        if ($planId <= 0) {
            return ['success' => false, 'message' => 'Não há plano mensal disponível para aplicar o mês grátis.'];
        }

        $start = $today->format('Y-m-d');
        $end = (new DateTime($start))->modify('+' . $months . ' months')->format('Y-m-d');

        $stmt = $db->prepare("
            INSERT INTO subscriptions (user_id, plan_id, status, start_date, end_date, auto_renew)
            VALUES (?, ?, 'active', ?, ?, 1)
        ");
        $stmt->execute([$userId, $planId, $start, $end]);
        return ['success' => true, 'message' => 'Mês grátis ativado. Assinatura válida até ' . date('d/m/Y', strtotime($end)) . '.'];
    } catch(PDOException $e) {
        return ['success' => false, 'message' => 'Erro ao aplicar mês grátis.'];
    }
}

function getActiveExtraScreensCount($db, $userId, $excludeUserRewardId = null) {
    try {
        if (!tableExists($db, 'user_rewards')) {
            return 0;
        }

        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM user_rewards ur
            JOIN rewards r ON r.id = ur.reward_id
            WHERE ur.user_id = ?
              AND r.reward_type = 'upgrade'
              AND (r.reward_value LIKE 'extra_screen:%' OR r.name LIKE '%Tela Extra%')
              AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
              AND ur.status IN ('redeemed','used')
              AND (? IS NULL OR ur.id <> ?)
        ");
        $stmt->execute([$userId, $excludeUserRewardId, $excludeUserRewardId]);
        return (int)$stmt->fetchColumn();
    } catch(PDOException $e) {
        return 0;
    }
}

// Função para buscar usuário por ID
function getUserById($db, $id) {
    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch(PDOException $e) {
        return null;
    }
}

// Função para buscar prêmio por ID
function getReward($db, $id) {
    try {
        $stmt = $db->prepare("
            SELECT *
            FROM rewards
            WHERE id = ?
              AND is_active = 1
              AND (valid_until IS NULL OR valid_until >= CURDATE())
              AND (max_redemptions IS NULL OR current_redemptions < max_redemptions)
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch(PDOException $e) {
        return null;
    }
}

// Função para formatar preço
function formatPrice($price) {
    return 'R$ ' . number_format($price, 2, ',', '.');
}

// Funções para gerenciamento de planos
function createPlan($db, $name, $duration_months, $price, $discount = 0, $description = '', $is_active = true) {
    try {
        $original_price = $price;
        $savings = ($price * $discount) / 100;
        $final_price = $price - $savings;
        
        $stmt = $db->prepare("INSERT INTO plans (name, duration_months, price, original_price, savings, features, is_popular) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $duration_months, $final_price, $original_price, $savings, $description, false]);
        return $db->lastInsertId();
    } catch(PDOException $e) {
        return false;
    }
}

function updatePlan($db, $id, $name, $duration_months, $price, $discount = 0, $description = '', $is_active = true) {
    try {
        $original_price = $price;
        $savings = ($price * $discount) / 100;
        $final_price = $price - $savings;
        
        $stmt = $db->prepare("UPDATE plans SET name = ?, duration_months = ?, price = ?, original_price = ?, savings = ?, features = ? WHERE id = ?");
        $stmt->execute([$name, $duration_months, $final_price, $original_price, $savings, $description, $id]);
        return true;
    } catch(PDOException $e) {
        return false;
    }
}

function deletePlan($db, $id) {
    try {
        // Verificar se há assinaturas ativas com este plano
        $stmt = $db->prepare("SELECT COUNT(*) FROM subscriptions WHERE plan_id = ? AND status = 'active'");
        $stmt->execute([$id]);
        $activeSubscriptions = $stmt->fetchColumn();
        
        if ($activeSubscriptions > 0) {
            return false; // Não pode deletar plano com assinaturas ativas
        }
        
        $stmt = $db->prepare("DELETE FROM plans WHERE id = ?");
        $stmt->execute([$id]);
        return true;
    } catch(PDOException $e) {
        return false;
    }
}

function togglePlanStatus($db, $id, $is_active) {
    try {
        // Como a tabela não tem campo is_active, vamos usar is_popular como status
        $stmt = $db->prepare("UPDATE plans SET is_popular = ? WHERE id = ?");
        $stmt->execute([$is_active, $id]);
        return true;
    } catch(PDOException $e) {
        return false;
    }
}

// Função para buscar planos ativos (para exibição no frontend)
function getActivePlans($db) {
    try {
        // Por enquanto, retorna todos os planos. Pode ser modificado para filtrar por status
        $stmt = $db->prepare("SELECT * FROM plans ORDER BY duration_months ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        return getPlans($db); // Fallback para todos os planos
    }
}

// ===== FUNÇÕES DE GERENCIAMENTO DE REGRAS DE PONTOS =====

// Função para buscar todas as regras de pontos
function getPointsRules($db) {
    try {
        $stmt = $db->prepare("SELECT * FROM points_rules ORDER BY action_type, name");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        return [];
    }
}

// Função para buscar regras ativas de pontos
function getActivePointsRules($db) {
    try {
        $stmt = $db->prepare("SELECT * FROM points_rules WHERE is_active = 1 ORDER BY action_type, name");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        return [];
    }
}

// Função para buscar uma regra específica
function getPointsRule($db, $id) {
    try {
        $stmt = $db->prepare("SELECT * FROM points_rules WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch(PDOException $e) {
        return false;
    }
}

// Função para criar nova regra de pontos
function createPointsRule($db, $data) {
    try {
        $stmt = $db->prepare("
            INSERT INTO points_rules (name, description, action_type, points_awarded, conditions_json, is_active, max_per_user, max_per_day) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $data['name'],
            $data['description'],
            $data['action_type'],
            $data['points_awarded'],
            $data['conditions_json'] ?? '{}',
            $data['is_active'] ?? 1,
            $data['max_per_user'] ?? null,
            $data['max_per_day'] ?? null
        ]);
    } catch(PDOException $e) {
        return false;
    }
}

// Função para atualizar regra de pontos
function updatePointsRule($db, $id, $data) {
    try {
        $stmt = $db->prepare("
            UPDATE points_rules 
            SET name = ?, description = ?, action_type = ?, points_awarded = ?, 
                conditions_json = ?, is_active = ?, max_per_user = ?, max_per_day = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['name'],
            $data['description'],
            $data['action_type'],
            $data['points_awarded'],
            $data['conditions_json'] ?? '{}',
            $data['is_active'] ?? 1,
            $data['max_per_user'] ?? null,
            $data['max_per_day'] ?? null,
            $id
        ]);
    } catch(PDOException $e) {
        return false;
    }
}

// Função para deletar regra de pontos
function deletePointsRule($db, $id) {
    try {
        $stmt = $db->prepare("DELETE FROM points_rules WHERE id = ?");
        return $stmt->execute([$id]);
    } catch(PDOException $e) {
        return false;
    }
}

// Função para alternar status da regra
function togglePointsRuleStatus($db, $id) {
    try {
        $stmt = $db->prepare("UPDATE points_rules SET is_active = NOT is_active WHERE id = ?");
        return $stmt->execute([$id]);
    } catch(PDOException $e) {
        return false;
    }
}

// Função para adicionar pontos baseado em uma regra
function awardPointsByRule($db, $userId, $actionType, $referenceId = null, $customData = []) {
    try {
        // Buscar regras ativas para o tipo de ação
        $stmt = $db->prepare("SELECT * FROM points_rules WHERE action_type = ? AND is_active = 1");
        $stmt->execute([$actionType]);
        $rules = $stmt->fetchAll();
        
        $totalPointsAwarded = 0;
        
        foreach ($rules as $rule) {
            // Verificar limites diários e por usuário
            if (!checkPointsLimits($db, $userId, $rule['id'], $rule)) {
                continue;
            }
            
            // Verificar condições específicas da regra
            if (!checkRuleConditions($rule, $customData)) {
                continue;
            }
            
            // Adicionar pontos
            $pointsAwarded = (int)($rule['points_awarded'] ?? 0);
            $conditions = json_decode($rule['conditions_json'] ?? '{}', true);
            if (is_array($conditions)) {
                if (isset($conditions['points_per_real']) && isset($customData['amount'])) {
                    $pointsAwarded = (int)round(((float)$customData['amount']) * (float)$conditions['points_per_real']);
                }

                if (isset($conditions['multiplier_by_payment_method']) && isset($customData['payment_method'])) {
                    $method = (string)$customData['payment_method'];
                    $methodMultiplier = $conditions['multiplier_by_payment_method'][$method] ?? 1.0;
                    $pointsAwarded = (int)round($pointsAwarded * (float)$methodMultiplier);
                }

                if (!empty($conditions['apply_vip_multiplier'])) {
                    $vip = getVipInfo($db, $userId);
                    $pointsAwarded = (int)round($pointsAwarded * (float)$vip['multiplier']);
                }

                if (isset($conditions['multiplier'])) {
                    $pointsAwarded = (int)round($pointsAwarded * (float)$conditions['multiplier']);
                }
            }

            if ($pointsAwarded <= 0) {
                continue;
            }
            addPoints($db, $userId, $pointsAwarded);
            
            insertPointsHistory(
                $db,
                $userId,
                (int)$rule['id'],
                $pointsAwarded,
                $actionType,
                $rule['name'] . ': ' . $rule['description'],
                $referenceId
            );
            
            $totalPointsAwarded += $pointsAwarded;
        }
        syncUserPointsBalance($db, $userId, 90);
        return $totalPointsAwarded;
    } catch(PDOException $e) {
        return 0;
    }
}

// Função para verificar limites de pontos
function checkPointsLimits($db, $userId, $ruleId, $rule) {
    try {
        // Verificar limite por usuário
        if ($rule['max_per_user'] !== null) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM points_history WHERE user_id = ? AND rule_id = ?");
            $stmt->execute([$userId, $ruleId]);
            if ($stmt->fetchColumn() >= $rule['max_per_user']) {
                return false;
            }
        }
        
        // Verificar limite diário
        if ($rule['max_per_day'] !== null) {
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM points_history 
                WHERE user_id = ? AND rule_id = ? AND DATE(created_at) = CURDATE()
            ");
            $stmt->execute([$userId, $ruleId]);
            if ($stmt->fetchColumn() >= $rule['max_per_day']) {
                return false;
            }
        }
        
        return true;
    } catch(PDOException $e) {
        return false;
    }
}

// Função para verificar condições específicas da regra
function checkRuleConditions($rule, $customData) {
    $conditions = json_decode($rule['conditions_json'], true);
    if (!$conditions) {
        return true;
    }
    
    // Implementar verificações específicas baseadas no tipo de ação
    switch ($rule['action_type']) {
        case 'payment':
            if (isset($conditions['min_amount']) && isset($customData['amount'])) {
                return $customData['amount'] >= $conditions['min_amount'];
            }
            break;
        case 'referral':
            if (isset($conditions['requires_signup']) && $conditions['requires_signup']) {
                return isset($customData['signup_completed']) && $customData['signup_completed'];
            }
            break;
        case 'review':
            if (isset($conditions['min_rating']) && isset($customData['rating'])) {
                return $customData['rating'] >= $conditions['min_rating'];
            }
            break;
    }
    
    return true;
}

// Função para buscar histórico de pontos do usuário
function getUserPointsHistory($db, $userId, $limit = 50) {
    try {
        $stmt = $db->prepare("
            SELECT ph.*, pr.name as rule_name 
            FROM points_history ph 
            LEFT JOIN points_rules pr ON ph.rule_id = pr.id 
            WHERE ph.user_id = ? 
            ORDER BY ph.created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        return [];
    }
}

// Função para gerar token de segurança
function generateToken() {
    return bin2hex(random_bytes(32));
}

// Função para validar email (removida - já existe em api.php)

// Função para validar telefone brasileiro
function isValidPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return strlen($phone) >= 10 && strlen($phone) <= 11;
}

// Função para sanitizar entrada (removida - já existe em api.php)

// Função para processar pagamento e conceder pontos
function processPayment($db, $subscriptionId, $amount, $paymentMethod = 'pix') {
    try {
        // Buscar dados da assinatura
        $stmt = $db->prepare("
            SELECT s.*, u.id as user_id, p.price 
            FROM subscriptions s 
            JOIN users u ON s.user_id = u.id 
            JOIN plans p ON s.plan_id = p.id 
            WHERE s.id = ?
        ");
        $stmt->execute([$subscriptionId]);
        $subscription = $stmt->fetch();
        
        if (!$subscription) {
            return false;
        }
        
        // Registrar pagamento (removendo user_id pois não existe na tabela payments)
        $stmt = $db->prepare("
            INSERT INTO payments (subscription_id, amount, payment_method, status, paid_at) 
            VALUES (?, ?, ?, 'completed', NOW())
        ");
        $stmt->execute([$subscriptionId, $amount, $paymentMethod]);
        $paymentId = $db->lastInsertId();
        
        // Atualizar status da assinatura
        $stmt = $db->prepare("UPDATE subscriptions SET status = 'active' WHERE id = ?");
        $stmt->execute([$subscriptionId]);
        
        // Conceder pontos por pagamento
        if ($paymentId) {
            awardPointsByRule($db, $subscription['user_id'], 'payment', $paymentId, [
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'subscription_id' => $subscriptionId
            ]);

            processReferralFirstPayment($db, (int)$subscription['user_id'], (float)$amount, (string)$paymentMethod, (int)$paymentId);
        }
        
        return $paymentId;
    } catch(PDOException $e) {
        error_log("Erro no processPayment: " . $e->getMessage());
        return false;
    }
}

function processReferralFirstPayment($db, $referredUserId, $amount, $paymentMethod, $paymentId) {
    try {
        $stmt = $db->prepare("
            SELECT * FROM referrals
            WHERE referred_user_id = ?
              AND status = 'completed'
              AND points_awarded = 0
            ORDER BY created_at ASC
            LIMIT 1
        ");
        $stmt->execute([$referredUserId]);
        $referral = $stmt->fetch();
        if (!$referral) {
            return false;
        }

        $referrerId = (int)$referral['referrer_id'];

        $stmt = $db->prepare("
            SELECT COUNT(*) FROM points_history
            WHERE user_id = ?
              AND action_type = 'referral'
              AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
              AND points_earned > 0
        ");
        $stmt->execute([$referrerId]);
        $monthCount = (int)$stmt->fetchColumn();
        if ($monthCount >= 5) {
            return false;
        }

        $awarded = awardPointsByRule($db, $referrerId, 'referral', (int)$referral['id'], [
            'referred_user_id' => $referredUserId,
            'first_payment' => true,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'payment_id' => $paymentId
        ]);

        if ($awarded > 0) {
            $stmt = $db->prepare("UPDATE referrals SET points_awarded = ?, completed_at = NOW() WHERE id = ?");
            $stmt->execute([(int)$awarded, (int)$referral['id']]);

            awardPointsByRule($db, $referredUserId, 'referral_referred_bonus', (int)$referral['id'], [
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'payment_id' => $paymentId
            ]);
            return true;
        }

        return false;
    } catch(PDOException $e) {
        return false;
    }
}

// Função para conceder pontos por login diário
function awardDailyLoginPoints($db, $userId) {
    try {
        // Verificar se já ganhou pontos hoje
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM points_history 
            WHERE user_id = ? AND action_type = 'login_streak' AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$userId]);
        
        if ($stmt->fetchColumn() == 0) {
            return awardPointsByRule($db, $userId, 'login_streak', null, [
                'login_date' => date('Y-m-d')
            ]);
        }
        
        return 0;
    } catch(PDOException $e) {
        return 0;
    }
}

// Função para conceder pontos por compartilhamento social
function awardSocialSharePoints($db, $userId, $platform) {
    try {
        return awardPointsByRule($db, $userId, 'social_share', null, [
            'platform' => $platform,
            'share_date' => date('Y-m-d H:i:s')
        ]);
    } catch(PDOException $e) {
        return 0;
    }
}

// Função para conceder pontos por avaliação
function awardReviewPoints($db, $userId, $rating, $reviewText = '') {
    try {
        return awardPointsByRule($db, $userId, 'review', null, [
            'rating' => $rating,
            'review_text' => $reviewText,
            'review_date' => date('Y-m-d H:i:s')
        ]);
    } catch(PDOException $e) {
        return 0;
    }
}

?>
