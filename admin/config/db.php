<?php
// admin/config/db.php

// DB Credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'Stephan2k03');
define('DB_NAME', 'scottsub_db');

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Admin DB Connection Failed: " . $e->getMessage());
}

// Admin Base URL
define('ADMIN_URL', 'http://localhost:8889/');

// if (!defined('EXCHANGE_RATE')) {
//     define('EXCHANGE_RATE', 4100); // 1 USD = 4100 MMK
// }

session_start();
?>