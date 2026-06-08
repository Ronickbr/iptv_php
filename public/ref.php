<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Verificar se foi fornecido um código de referência
if (!isset($_GET['code']) || empty($_GET['code'])) {
    header('Location: index.php');
    exit;
}

$referralCode = trim($_GET['code']);
$db = getDB();

// Buscar usuário pelo código de referência
$referrer = getUserByReferralCode($db, $referralCode);

if (!$referrer) {
    // Código inválido, redirecionar para página inicial
    header('Location: index.php?error=invalid_referral');
    exit;
}

// Armazenar o ID do indicador na sessão
$_SESSION['referrer_id'] = $referrer['id'];
$_SESSION['referrer_name'] = $referrer['name'];
$_SESSION['referral_code'] = $referralCode;

// Redirecionar para a página de cadastro com mensagem de indicação
header('Location: subscribe.php?referred_by=' . urlencode($referrer['name']) . '&ref_code=' . urlencode($referralCode));
exit;
?>
