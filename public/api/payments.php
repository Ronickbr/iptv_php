<?php
/**
 * Endpoint de Gerenciamento de Pagamentos
 * KMKZ IPTV API
 * 
 * Endpoints disponíveis:
 * GET /payments.php?action=list - Listar pagamentos
 * GET /payments.php?action=get&id={id} - Obter pagamento específico
 * POST /payments.php?action=create - Criar pagamento
 * POST /payments.php?action=update_status - Atualizar status do pagamento
 * POST /payments.php?action=process_webhook - Processar webhook de pagamento
 * GET /payments.php?action=user_payments - Pagamentos do usuário logado
 * GET /payments.php?action=stats - Estatísticas de pagamentos (admin)
 * POST /payments.php?action=generate_pix - Gerar código PIX
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/middleware.php';

// Aplicar middleware de segurança
ApiMiddleware::apply();

// Obter ação
$action = $_GET['action'] ?? '';
$input = ApiUtils::getInput();

// Conectar ao banco
$db = getDB();
if (!$db) {
    echo ApiUtils::error('Erro de conexão com o banco de dados');
    exit();
}

switch ($action) {
    case 'list':
        handleListPayments($db, $input);
        break;
        
    case 'get':
        handleGetPayment($db, $input);
        break;
        
    case 'create':
        handleCreatePayment($db, $input);
        break;
        
    case 'update_status':
        handleUpdatePaymentStatus($db, $input);
        break;
        
    case 'process_webhook':
        handleProcessWebhook($db, $input);
        break;
        
    case 'user_payments':
        handleUserPayments($db);
        break;
        
    case 'stats':
        handlePaymentStats($db);
        break;
        
    case 'generate_pix':
        handleGeneratePix($db, $input);
        break;
        
    default:
        echo ApiUtils::error('Ação não encontrada', 404);
        break;
}

/**
 * Listar pagamentos (admin)
 */
function handleListPayments($db, $input) {
    ApiUtils::validateMethod(['GET']);
    ApiUtils::requireAdmin();
    
    try {
        // Parâmetros de paginação
        $pagination = ApiUtils::getPagination(
            $input['page'] ?? 1,
            $input['per_page'] ?? DEFAULT_PAGE_SIZE
        );
        
        // Filtros
        $status = $input['status'] ?? '';
        $paymentMethod = $input['payment_method'] ?? '';
        $subscriptionId = $input['subscription_id'] ?? '';
        $userId = $input['user_id'] ?? '';
        $dateFrom = $input['date_from'] ?? '';
        $dateTo = $input['date_to'] ?? '';
        
        // Construir query
        $whereConditions = [];
        $params = [];
        
        if (!empty($status)) {
            $whereConditions[] = "p.status = ?";
            $params[] = $status;
        }
        
        if (!empty($paymentMethod)) {
            $whereConditions[] = "p.payment_method = ?";
            $params[] = $paymentMethod;
        }
        
        if (!empty($subscriptionId)) {
            $whereConditions[] = "p.subscription_id = ?";
            $params[] = $subscriptionId;
        }
        
        if (!empty($userId)) {
            $whereConditions[] = "s.user_id = ?";
            $params[] = $userId;
        }
        
        if (!empty($dateFrom)) {
            $whereConditions[] = "DATE(p.created_at) >= ?";
            $params[] = $dateFrom;
        }
        
        if (!empty($dateTo)) {
            $whereConditions[] = "DATE(p.created_at) <= ?";
            $params[] = $dateTo;
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // Contar total
        $countQuery = "
            SELECT COUNT(*) as total 
            FROM payments p
            LEFT JOIN subscriptions s ON p.subscription_id = s.id
            LEFT JOIN users u ON s.user_id = u.id
            {$whereClause}
        ";
        $stmt = $db->prepare($countQuery);
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];
        
        // Buscar pagamentos
        $query = "
            SELECT 
                p.*,
                s.id as subscription_id,
                u.name as user_name,
                u.email as user_email,
                pl.name as plan_name
            FROM payments p
            LEFT JOIN subscriptions s ON p.subscription_id = s.id
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN plans pl ON s.plan_id = pl.id
            {$whereClause}
            ORDER BY p.created_at DESC
            LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $payments = $stmt->fetchAll();
        
        // Calcular paginação
        $paginationInfo = ApiUtils::calculatePagination($total, $pagination['page'], $pagination['per_page']);
        
        echo ApiUtils::success($payments, 'Pagamentos listados com sucesso', $paginationInfo);
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao listar pagamentos');
    }
}

/**
 * Obter pagamento específico
 */
function handleGetPayment($db, $input) {
    ApiUtils::validateMethod(['GET']);
    ApiUtils::requireAuth();
    
    $paymentId = $input['id'] ?? null;
    
    if (!$paymentId) {
        echo ApiUtils::error('ID do pagamento é obrigatório', 400);
        return;
    }
    
    try {
        // Buscar pagamento
        $stmt = $db->prepare("
            SELECT 
                p.*,
                s.id as subscription_id,
                s.user_id,
                u.name as user_name,
                u.email as user_email,
                pl.name as plan_name,
                pl.description as plan_description
            FROM payments p
            LEFT JOIN subscriptions s ON p.subscription_id = s.id
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN plans pl ON s.plan_id = pl.id
            WHERE p.id = ?
        ");
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch();
        
        if (!$payment) {
            echo ApiUtils::error('Pagamento não encontrado', 404);
            return;
        }
        
        // Verificar permissão (usuário só pode ver próprios pagamentos)
        if ($_SESSION['user_type'] !== 'admin' && $payment['user_id'] != $_SESSION['user_id']) {
            echo ApiUtils::error('Acesso negado', 403);
            return;
        }
        
        echo ApiUtils::success($payment, 'Pagamento obtido com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao obter pagamento');
    }
}

/**
 * Criar pagamento
 */
function handleCreatePayment($db, $input) {
    ApiUtils::validateMethod(['POST']);
    ApiUtils::requireAuth();
    ApiUtils::validateRequired($input, ['subscription_id', 'amount', 'payment_method']);
    
    $subscriptionId = $input['subscription_id'];
    $amount = $input['amount'];
    $paymentMethod = $input['payment_method'];
    $description = $input['description'] ?? '';
    
    try {
        // Verificar se assinatura existe
        $stmt = $db->prepare("
            SELECT s.*, u.id as user_id 
            FROM subscriptions s 
            JOIN users u ON s.user_id = u.id 
            WHERE s.id = ?
        ");
        $stmt->execute([$subscriptionId]);
        $subscription = $stmt->fetch();
        
        if (!$subscription) {
            echo ApiUtils::error('Assinatura não encontrada', 404);
            return;
        }
        
        // Verificar permissão
        if ($_SESSION['user_type'] !== 'admin' && $subscription['user_id'] != $_SESSION['user_id']) {
            echo ApiUtils::error('Acesso negado', 403);
            return;
        }
        
        // Validar valor
        if (!is_numeric($amount) || $amount <= 0) {
            echo ApiUtils::error('Valor inválido', 400);
            return;
        }
        
        // Validar método de pagamento
        $allowedMethods = ['pix', 'credit_card', 'debit_card', 'bank_transfer', 'boleto'];
        if (!in_array($paymentMethod, $allowedMethods)) {
            echo ApiUtils::error('Método de pagamento inválido', 400);
            return;
        }
        
        // Criar pagamento
        $stmt = $db->prepare("
            INSERT INTO payments (subscription_id, amount, payment_method, description, status, created_at) 
            VALUES (?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$subscriptionId, $amount, $paymentMethod, $description]);
        
        $paymentId = $db->lastInsertId();
        
        // Gerar dados específicos do método de pagamento
        $paymentData = generatePaymentData($paymentMethod, $amount, $paymentId);
        
        // Atualizar pagamento com dados específicos
        if (!empty($paymentData)) {
            $stmt = $db->prepare("UPDATE payments SET payment_data = ? WHERE id = ?");
            $stmt->execute([json_encode($paymentData), $paymentId]);
        }
        
        // Log da ação
        ApiUtils::logActivity('payment_created', [
            'payment_id' => $paymentId,
            'subscription_id' => $subscriptionId,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'created_by' => $_SESSION['user_id']
        ]);
        
        $result = [
            'payment_id' => $paymentId,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'status' => 'pending',
            'payment_data' => $paymentData
        ];
        
        echo ApiUtils::success($result, 'Pagamento criado com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao criar pagamento');
    }
}

/**
 * Atualizar status do pagamento
 */
function handleUpdatePaymentStatus($db, $input) {
    ApiUtils::validateMethod(['POST', 'PUT']);
    ApiUtils::requireAdmin();
    ApiUtils::validateRequired($input, ['payment_id', 'status']);
    
    $paymentId = $input['payment_id'];
    $newStatus = $input['status'];
    $notes = $input['notes'] ?? '';
    
    try {
        // Verificar se pagamento existe
        $stmt = $db->prepare("
            SELECT p.*, s.user_id, s.plan_id 
            FROM payments p 
            JOIN subscriptions s ON p.subscription_id = s.id 
            WHERE p.id = ?
        ");
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch();
        
        if (!$payment) {
            echo ApiUtils::error('Pagamento não encontrado', 404);
            return;
        }
        
        // Validar status
        $allowedStatuses = ['pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded'];
        if (!in_array($newStatus, $allowedStatuses)) {
            echo ApiUtils::error('Status inválido', 400);
            return;
        }
        
        $oldStatus = $payment['status'];
        
        // Atualizar status
        $stmt = $db->prepare("
            UPDATE payments 
            SET status = ?, notes = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$newStatus, $notes, $paymentId]);
        
        // Se pagamento foi aprovado, ativar assinatura
        if ($newStatus === 'completed' && $oldStatus !== 'completed') {
            $stmt = $db->prepare("UPDATE subscriptions SET status = 'active' WHERE id = ?");
            $stmt->execute([$payment['subscription_id']]);
            
            // Conceder pontos por pagamento
            awardPointsByRule($db, $payment['user_id'], 'payment', $paymentId, [
                'amount' => $payment['amount'],
                'payment_method' => $payment['payment_method'],
                'subscription_id' => $payment['subscription_id'],
                'plan_id' => $payment['plan_id']
            ]);
        }
        
        // Se pagamento foi cancelado/falhou, suspender assinatura
        if (in_array($newStatus, ['failed', 'cancelled']) && $oldStatus === 'completed') {
            $stmt = $db->prepare("UPDATE subscriptions SET status = 'suspended' WHERE id = ?");
            $stmt->execute([$payment['subscription_id']]);
        }
        
        // Log da ação
        ApiUtils::logActivity('payment_status_updated', [
            'payment_id' => $paymentId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'notes' => $notes,
            'updated_by' => $_SESSION['user_id']
        ]);
        
        echo ApiUtils::success(null, 'Status do pagamento atualizado com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao atualizar status do pagamento');
    }
}

/**
 * Processar webhook de pagamento
 */
function handleProcessWebhook($db, $input) {
    ApiUtils::validateMethod(['POST']);
    
    // Não requer autenticação pois vem de gateway externo
    // Mas deve validar assinatura/token se necessário
    
    try {
        $paymentId = $input['payment_id'] ?? null;
        $status = $input['status'] ?? null;
        $transactionId = $input['transaction_id'] ?? null;
        $gateway = $input['gateway'] ?? 'unknown';
        
        if (!$paymentId || !$status) {
            echo ApiUtils::error('Dados insuficientes no webhook', 400);
            return;
        }
        
        // Verificar se pagamento existe
        $stmt = $db->prepare("
            SELECT p.*, s.user_id, s.plan_id 
            FROM payments p 
            JOIN subscriptions s ON p.subscription_id = s.id 
            WHERE p.id = ?
        ");
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch();
        
        if (!$payment) {
            echo ApiUtils::error('Pagamento não encontrado', 404);
            return;
        }
        
        // Mapear status do gateway para status interno
        $statusMap = [
            'approved' => 'completed',
            'paid' => 'completed',
            'success' => 'completed',
            'rejected' => 'failed',
            'failed' => 'failed',
            'cancelled' => 'cancelled',
            'pending' => 'pending',
            'processing' => 'processing'
        ];
        
        $newStatus = $statusMap[$status] ?? 'pending';
        $oldStatus = $payment['status'];
        
        // Atualizar pagamento
        $stmt = $db->prepare("
            UPDATE payments 
            SET status = ?, transaction_id = ?, gateway_response = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([
            $newStatus, 
            $transactionId, 
            json_encode($input), 
            $paymentId
        ]);
        
        // Processar mudança de status
        if ($newStatus === 'completed' && $oldStatus !== 'completed') {
            // Ativar assinatura
            $stmt = $db->prepare("UPDATE subscriptions SET status = 'active' WHERE id = ?");
            $stmt->execute([$payment['subscription_id']]);
            
            // Conceder pontos
            awardPointsByRule($db, $payment['user_id'], 'payment', $paymentId, [
                'amount' => $payment['amount'],
                'payment_method' => $payment['payment_method'],
                'subscription_id' => $payment['subscription_id'],
                'plan_id' => $payment['plan_id']
            ]);
        }
        
        // Log do webhook
        ApiUtils::logActivity('webhook_processed', [
            'payment_id' => $paymentId,
            'gateway' => $gateway,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'transaction_id' => $transactionId
        ]);
        
        echo ApiUtils::success(null, 'Webhook processado com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao processar webhook');
    }
}

/**
 * Pagamentos do usuário logado
 */
function handleUserPayments($db) {
    ApiUtils::validateMethod(['GET']);
    ApiUtils::requireAuth();
    
    try {
        // Buscar pagamentos do usuário
        $stmt = $db->prepare("
            SELECT 
                p.*,
                s.id as subscription_id,
                pl.name as plan_name,
                pl.description as plan_description
            FROM payments p
            JOIN subscriptions s ON p.subscription_id = s.id
            JOIN plans pl ON s.plan_id = pl.id
            WHERE s.user_id = ?
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $payments = $stmt->fetchAll();
        
        // Remover dados sensíveis
        foreach ($payments as &$payment) {
            unset($payment['gateway_response']);
            
            // Decodificar payment_data se existir
            if (!empty($payment['payment_data'])) {
                $paymentData = json_decode($payment['payment_data'], true);
                $payment['payment_data'] = $paymentData;
            }
        }
        
        echo ApiUtils::success($payments, 'Pagamentos obtidos com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao obter pagamentos');
    }
}

/**
 * Estatísticas de pagamentos (admin)
 */
function handlePaymentStats($db) {
    ApiUtils::validateMethod(['GET']);
    ApiUtils::requireAdmin();
    
    try {
        // Total de pagamentos
        $stmt = $db->query("SELECT COUNT(*) as total FROM payments");
        $totalPayments = $stmt->fetch()['total'];
        
        // Pagamentos por status
        $stmt = $db->query("SELECT status, COUNT(*) as count FROM payments GROUP BY status");
        $paymentsByStatus = $stmt->fetchAll();
        
        // Receita total
        $stmt = $db->query("SELECT SUM(amount) as total FROM payments WHERE status = 'completed'");
        $totalRevenue = $stmt->fetch()['total'] ?? 0;
        
        // Receita por método de pagamento
        $stmt = $db->query("
            SELECT 
                payment_method,
                COUNT(*) as count,
                SUM(amount) as revenue
            FROM payments 
            WHERE status = 'completed'
            GROUP BY payment_method
        ");
        $revenueByMethod = $stmt->fetchAll();
        
        // Receita diária (últimos 30 dias)
        $stmt = $db->query("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as payments,
                SUM(amount) as revenue
            FROM payments 
            WHERE status = 'completed' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $dailyRevenue = $stmt->fetchAll();
        
        // Taxa de aprovação
        $stmt = $db->query("
            SELECT 
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                COUNT(*) as total
            FROM payments
        ");
        $approvalData = $stmt->fetch();
        $approvalRate = $approvalData['total'] > 0 ? 
            ($approvalData['completed'] / $approvalData['total']) * 100 : 0;
        
        // Ticket médio
        $stmt = $db->query("SELECT AVG(amount) as avg_amount FROM payments WHERE status = 'completed'");
        $averageTicket = $stmt->fetch()['avg_amount'] ?? 0;
        
        // Pagamentos pendentes
        $stmt = $db->query("SELECT COUNT(*) as total FROM payments WHERE status = 'pending'");
        $pendingPayments = $stmt->fetch()['total'];
        
        $stats = [
            'total_payments' => $totalPayments,
            'payments_by_status' => $paymentsByStatus,
            'total_revenue' => $totalRevenue,
            'revenue_by_method' => $revenueByMethod,
            'daily_revenue' => $dailyRevenue,
            'approval_rate' => round($approvalRate, 2),
            'average_ticket' => round($averageTicket, 2),
            'pending_payments' => $pendingPayments
        ];
        
        echo ApiUtils::success($stats, 'Estatísticas obtidas com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao obter estatísticas');
    }
}

/**
 * Gerar código PIX
 */
function handleGeneratePix($db, $input) {
    ApiUtils::validateMethod(['POST']);
    ApiUtils::requireAuth();
    ApiUtils::validateRequired($input, ['payment_id']);
    
    $paymentId = $input['payment_id'];
    
    try {
        // Verificar se pagamento existe
        $stmt = $db->prepare("
            SELECT p.*, s.user_id 
            FROM payments p 
            JOIN subscriptions s ON p.subscription_id = s.id 
            WHERE p.id = ?
        ");
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch();
        
        if (!$payment) {
            echo ApiUtils::error('Pagamento não encontrado', 404);
            return;
        }
        
        // Verificar permissão
        if ($_SESSION['user_type'] !== 'admin' && $payment['user_id'] != $_SESSION['user_id']) {
            echo ApiUtils::error('Acesso negado', 403);
            return;
        }
        
        // Verificar se é pagamento PIX
        if ($payment['payment_method'] !== 'pix') {
            echo ApiUtils::error('Pagamento não é do tipo PIX', 400);
            return;
        }
        
        // Gerar código PIX (simulado)
        $pixData = generatePixCode($payment['amount'], $paymentId);
        
        // Atualizar pagamento com dados do PIX
        $stmt = $db->prepare("UPDATE payments SET payment_data = ? WHERE id = ?");
        $stmt->execute([json_encode($pixData), $paymentId]);
        
        echo ApiUtils::success($pixData, 'Código PIX gerado com sucesso');
        
    } catch (Exception $e) {
        echo ApiUtils::error('Erro ao gerar código PIX');
    }
}

/**
 * Gerar dados específicos do método de pagamento
 */
function generatePaymentData($paymentMethod, $amount, $paymentId) {
    switch ($paymentMethod) {
        case 'pix':
            return generatePixCode($amount, $paymentId);
            
        case 'boleto':
            return generateBoletoData($amount, $paymentId);
            
        case 'credit_card':
        case 'debit_card':
            return generateCardData($amount, $paymentId);
            
        default:
            return [];
    }
}

/**
 * Gerar código PIX (simulado)
 */
function generatePixCode($amount, $paymentId) {
    // Em produção, integrar com gateway de pagamento real
    $pixKey = 'contato@kmkziptv.com'; // Chave PIX da empresa
    $merchantName = 'KMKZ IPTV';
    $merchantCity = 'SAO PAULO';
    $txId = 'PAY' . str_pad($paymentId, 8, '0', STR_PAD_LEFT);
    
    // Gerar código PIX simplificado (em produção usar biblioteca específica)
    $pixCode = "00020126580014BR.GOV.BCB.PIX0136{$pixKey}0208{$txId}5204000053039865802BR5913{$merchantName}6009{$merchantCity}62070503***6304";
    
    // Calcular CRC16 (simplificado)
    $crc = sprintf('%04X', crc16($pixCode));
    $pixCode .= $crc;
    
    return [
        'pix_code' => $pixCode,
        'pix_key' => $pixKey,
        'amount' => $amount,
        'merchant_name' => $merchantName,
        'merchant_city' => $merchantCity,
        'transaction_id' => $txId,
        'expires_at' => date('Y-m-d H:i:s', strtotime('+30 minutes')),
        'qr_code_url' => "data:image/png;base64," . base64_encode(generateQRCode($pixCode))
    ];
}

/**
 * Gerar dados do boleto (simulado)
 */
function generateBoletoData($amount, $paymentId) {
    $dueDate = date('Y-m-d', strtotime('+3 days'));
    $barcode = '23793' . date('ymd', strtotime($dueDate)) . str_pad($paymentId, 10, '0', STR_PAD_LEFT);
    
    return [
        'barcode' => $barcode,
        'due_date' => $dueDate,
        'amount' => $amount,
        'bank' => '237 - Bradesco',
        'agency' => '1234-5',
        'account' => '12345-6',
        'document_number' => str_pad($paymentId, 8, '0', STR_PAD_LEFT),
        'pdf_url' => "/api/boleto.php?payment_id={$paymentId}"
    ];
}

/**
 * Gerar dados do cartão (simulado)
 */
function generateCardData($amount, $paymentId) {
    return [
        'amount' => $amount,
        'installments' => 1,
        'processor' => 'cielo',
        'transaction_id' => 'TXN' . str_pad($paymentId, 10, '0', STR_PAD_LEFT),
        'authorization_url' => "/api/card_payment.php?payment_id={$paymentId}"
    ];
}

/**
 * Calcular CRC16 (simplificado)
 */
function crc16($data) {
    $crc = 0xFFFF;
    for ($i = 0; $i < strlen($data); $i++) {
        $crc ^= ord($data[$i]) << 8;
        for ($j = 0; $j < 8; $j++) {
            if ($crc & 0x8000) {
                $crc = ($crc << 1) ^ 0x1021;
            } else {
                $crc = $crc << 1;
            }
        }
    }
    return $crc & 0xFFFF;
}

/**
 * Gerar QR Code (simulado)
 */
function generateQRCode($data) {
    // Em produção, usar biblioteca como phpqrcode
    // Por enquanto retorna um placeholder
    return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
}

?>