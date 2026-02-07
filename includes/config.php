<?php
// includes/config.php

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
    die("Database Connection Failed: " . $e->getMessage());
}

// System Settings
define('BASE_URL', 'http://digitalmarketplacemm.com/');
define('EXCHANGE_RATE', 4200); // 1 USD = 4200 MMK

// Start Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle Currency Switch
if (isset($_GET['set_curr'])) {
    $_SESSION['currency'] = $_GET['set_curr'];
    $url = strtok($_SERVER["REQUEST_URI"], '?');
    header("Location: $url");
    exit;
}

// Default Currency
if (!isset($_SESSION['currency'])) {
    $_SESSION['currency'] = 'MMK';
}
?>