<?php
// includes/config.php

// Database Config
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'Stephan2k03');
define('DB_NAME', 'scottsub_db');

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Connection Failed.");
}

// System Settings
define('BASE_URL', 'http://localhost:8546');
define('EXCHANGE_RATE', 4200); // 1 USD = 4200 MMK

session_start();

// Handle Currency Switch Request Globaly
if (isset($_GET['set_curr'])) {
    $_SESSION['currency'] = $_GET['set_curr']; // 'MMK' or 'USD'
    // Redirect back to remove query param
    $url = strtok($_SERVER["REQUEST_URI"], '?');
    header("Location: $url");
    exit;
}

// Default Currency
if (!isset($_SESSION['currency'])) {
    $_SESSION['currency'] = 'MMK';
}
?>