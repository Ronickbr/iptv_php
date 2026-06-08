<?php
require_once __DIR__ . '/../includes/api.php';

// Iniciar sessão para poder destruí-la
session_start();

// Fazer logout via API
$logoutResponse = apiLogout();

// Limpar todas as variáveis de sessão
$_SESSION = array();

// Destruir o cookie de sessão se existir
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir a sessão
session_destroy();

// Redirecionar para a página de login
header('Location: login.php?message=logout_success');
exit;
?>
