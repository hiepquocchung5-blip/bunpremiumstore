<?php
// includes/config.php

// 1. Load Composer Autoloader (Required for Dotenv)
require_once __DIR__ . '/../vendor/autoload.php';

// 2. Load .env file
// We use safeLoad() so it doesn't crash if the file is missing (useful if vars are set via server config)
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->safeLoad();
} catch (Exception $e) {
    error_log("Dotenv error: " . $e->getMessage());
}

// 3. Database Connection
define('DB_HOST', $_ENV['DB_HOST'] );
define('DB_USER', $_ENV['DB_USER'] );
define('DB_PASS', $_ENV['DB_PASS'] );
define('DB_NAME', $_ENV['DB_NAME'] );

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Ensure UTF-8 encoding
    $pdo->exec("set names utf8mb4");
} catch (PDOException $e) {
    // In production, log the error instead of showing it to the user
    error_log("DB Connection Failed: " . $e->getMessage());
    die("Service temporarily unavailable. Please try again later.");
}

// 4. System Settings
define('BASE_URL', $_ENV['APP_URL'] ?? 'http://digitalmarketplacemm.com/');
define('ADMIN_URL', $_ENV['ADMIN_URL'] ?? 'http://bunsadminconfig.digitalmarketplacemm.com/');
define('EXCHANGE_RATE', (int)($_ENV['EXCHANGE_RATE'] ?? 4200));

// VAPID Keys for Push Notifications
define('VAPID_PUBLIC_KEY', $_ENV['VAPID_PUBLIC_KEY'] ?? '');
define('VAPID_PRIVATE_KEY', $_ENV['VAPID_PRIVATE_KEY'] ?? '');
define('VAPID_SUBJECT', $_ENV['VAPID_SUBJECT'] ?? '');

// 5. Start Session
if (session_status() === PHP_SESSION_NONE) {
    // Secure session cookies
    $is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    
    session_set_cookie_params([
        'lifetime' => 86400 * 30, // 30 days
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => $is_https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// 6. Handle Currency Switch Logic
if (isset($_GET['set_curr'])) {
    $_SESSION['currency'] = $_GET['set_curr'];
    
    // Clean URL redirection to remove the GET parameter
    $url_path = strtok($_SERVER["REQUEST_URI"], '?');
    $query_params = $_GET;
    unset($query_params['set_curr']);
    
    $new_query_string = http_build_query($query_params);
    $redirect_url = $url_path . ($new_query_string ? '?' . $new_query_string : '');
    
    header("Location: " . $redirect_url);
    exit;
}

// Default Currency
if (!isset($_SESSION['currency'])) {
    $_SESSION['currency'] = 'MMK';
}
?>