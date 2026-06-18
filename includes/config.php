<?php
// includes/config.php

// 1. Load Composer Autoloader (Required for Dotenv)
require_once __DIR__ . '/../vendor/autoload.php';

// 2. Load .env file
// Using safeLoad allows the app to run if env vars are set via server config instead of file
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
    // Ensure UTF-8 encoding for Myanmar text support
    $pdo->exec("set names utf8mb4");
} catch (PDOException $e) {
    // In production, log the error instead of displaying raw DB info
    error_log("DB Connection Failed: " . $e->getMessage());
    http_response_code(500);
    include __DIR__ . '/../modules/error/500.php';
    exit;
}

// 4. System Settings
define('BASE_URL', $_ENV['APP_URL'] ?? 'https://digitalmarketplacemm.com/');
define('MAIN_SITE_URL', 'https://digitalmarketplacemm.com/'); // Point to where uploads folder lives
define('ADMIN_URL', $_ENV['ADMIN_URL'] ?? 'https://bunsadminconfig.digitalmarketplacemm.com/');
define('EXCHANGE_RATE', (int)($_ENV['EXCHANGE_RATE'] ?? 4200));

// VAPID Keys for Push Notifications
define('VAPID_PUBLIC_KEY', $_ENV['VAPID_PUBLIC_KEY'] ?? '');
define('VAPID_PRIVATE_KEY', $_ENV['VAPID_PRIVATE_KEY'] ?? '');
define('VAPID_SUBJECT', $_ENV['VAPID_SUBJECT'] ?? '');

// AI Configuration
$raw_gemini_key = $_ENV['GEMINI_API_KEY'] ?? '';
define('GEMINI_API_KEY', trim($raw_gemini_key));

// Telegram Configuration
define('TG_BOT_TOKEN', trim($_ENV['TG_BOT_TOKEN'] ?? ''));
define('TG_ADMIN_CHAT_ID', trim($_ENV['TG_ADMIN_CHAT_ID'] ?? ''));

// 5. Google OAuth Configuration
define('GOOGLE_CLIENT_ID', trim($_ENV['GOOGLE_CLIENT_ID'] ?? ''));
define('GOOGLE_CLIENT_SECRET', trim($_ENV['GOOGLE_CLIENT_SECRET'] ?? ''));
// Construct the Redirect URL based on BASE_URL
define('GOOGLE_REDIRECT_URL', BASE_URL . 'index.php?module=auth&page=login');

// 6. Start Session
if (session_status() === PHP_SESSION_NONE) {
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) == 443);
    $cookie_domain = $_SERVER['HTTP_HOST'] ?? '';
    if (strpos($cookie_domain, ':') !== false) {
        $cookie_domain = explode(':', $cookie_domain)[0];
    }
    
    session_set_cookie_params([
        'lifetime' => 86400 * 30,
        'path' => '/',
        'domain' => $cookie_domain ?: '',
        'secure' => $is_https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

$theme_cookie = $_COOKIE['site_theme'] ?? null;
if ($theme_cookie === 'light' || $theme_cookie === 'dark') {
    $_SESSION['theme'] = $theme_cookie;
} elseif (!isset($_SESSION['theme'])) {
    $_SESSION['theme'] = 'dark';
}

// 7. Handle Currency Switch Logic
// Allows changing currency via ?set_curr=USD anywhere in the app
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
