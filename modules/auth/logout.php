<?php
// modules/auth/logout.php

// 1. Initialize session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Unset all session variables
$_SESSION = array();

// 3. Delete the session cookie
// This effectively destroys the session on the client side
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Destroy the session storage on server
session_destroy();

// 5. Redirect to Login Page
header("Location: index.php?module=auth&page=login");
exit;
?>