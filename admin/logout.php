<?php
// admin/logout.php

// Include config to access the ADMIN_URL constant
require_once 'config/db.php';

// 1. Start Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Unset all session variables
$_SESSION = array();

// 3. Destroy Session Cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Destroy Session
session_destroy();

// 5. Redirect strictly to the Admin Subdomain
$redirect_url = defined('ADMIN_URL') ? ADMIN_URL . 'login.php' : '/login.php';
header("Location: " . $redirect_url);
exit;
?>