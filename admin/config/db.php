<?php
// admin/config/db.php

// ⚡️ LOAD ENVIRONMENT ( पॉप्युलेट $_ENV from root .env )
require_once dirname(__DIR__) . '/../vendor/autoload.php';
try {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__) . '/../');
    $dotenv->safeLoad();
} catch (Exception $e) {
    // Fails silently if .env is missing or unreadable
}

// Live Database Credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'zpnpszw1_buns_sub_usr');
define('DB_PASS', '@fekgygn85cCM43');
define('DB_NAME', 'zpnpszw1_bunspremiumsubshop');

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Admin DB Connection Failed: " . $e->getMessage());
}

// Admin Base URL (Subdomain)
define('BASE_URL', $_ENV['APP_URL'] ?? 'https://digitalmarketplacemm.com/');
define('ADMIN_URL', 'https://bunsadminconfig.digitalmarketplacemm.com/');
define('MAIN_SITE_URL', 'https://digitalmarketplacemm.com/'); // Point to where uploads folder lives

// Start Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>