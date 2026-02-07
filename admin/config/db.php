<?php
// admin/config/db.php

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
define('ADMIN_URL', 'http://bunsadminconfig.digitalmarketplacemm.com/');

// Start Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>