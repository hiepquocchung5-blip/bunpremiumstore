<?php
// admin/includes/functions.php

/**
 * --------------------------------------------------------------------------
 * ADMIN AUTHENTICATION
 * --------------------------------------------------------------------------
 */
function check_admin_auth() {
    // If session not started, start it
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        // Redirect to login if not authenticated
        header("Location: login.php");
        exit;
    }
}

if (!defined('EXCHANGE_RATE')) {
    define('EXCHANGE_RATE', 4200); // 1 USD = 4200 MMK
}

// Safe Redirect Helper
function redirect($url) {
    if (!headers_sent()) {
        header("Location: " . $url);
    } else {
        echo "<script>window.location.href='" . $url . "';</script>";
    }
    exit;
}

/**
 * --------------------------------------------------------------------------
 * ADMIN ROUTING SYSTEM
 * Handles ?page=orders logic to keep URLs clean
 * --------------------------------------------------------------------------
 */
function get_admin_view() {
    // Default page is dashboard
    $page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
    
    // Whitelist of allowed pages => filename
    // Note: You must ensure these files exist in the admin root folder
    $routes = [
        'dashboard'    => 'dashboard.php',
        'orders'       => 'orders.php',
        'order_detail' => 'order_detail.php',
        'products'     => 'products.php',
        'product_edit' => 'product_edit.php', 
        'categories'   => 'categories.php',
        'category_edit' => 'category_edit.php', 
        'keys'         => 'keys.php',
        'users'        => 'users.php',
        'user_detail'  => 'user_detail.php',
        'reviews'      => 'reviews.php',
        'reports'      => 'reports.php',
        'banners'      => 'banners.php',
        'payments'     => 'payments.php',
        'settings'     => 'settings.php'
    ];

    // Check if page exists in whitelist
    if (array_key_exists($page, $routes)) {
        $file = $routes[$page];
        
        // If the file exists, return it to be included
        // Using __DIR__ to ensure path is relative to includes folder
        $path = __DIR__ . '/../' . $file;
        if (file_exists($path)) {
            return $path;
        }
    }

    // Fallback: 404 or Dashboard
    return __DIR__ . '/../dashboard.php'; 
}

// Helper to generate admin URLs
function admin_url($page, $params = []) {
    $url = "index.php?page=" . urlencode($page);
    if (!empty($params)) {
        $url .= '&' . http_build_query($params);
    }
    return $url;
}

/**
 * --------------------------------------------------------------------------
 * FINANCIAL & REPORTING FUNCTIONS
 * --------------------------------------------------------------------------
 */

// Get Total Revenue (Sum of active orders)
function get_total_revenue($pdo) {
    try {
        $stmt = $pdo->query("SELECT SUM(total_price_paid) FROM orders WHERE status = 'active'");
        return (float) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0.00;
    }
}

// Get Total Expenses
function get_total_expenses($pdo) {
    try {
        $stmt = $pdo->query("SELECT SUM(amount) FROM expenses");
        return (float) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0.00;
    }
}

// Calculate Net Profit
function get_net_profit($pdo) {
    return get_total_revenue($pdo) - get_total_expenses($pdo);
}

// Get Pending Orders Count (For Sidebar Badge)
function get_pending_count($pdo) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * --------------------------------------------------------------------------
 * FORMATTING HELPERS
 * --------------------------------------------------------------------------
 */

// Format Currency
function format_admin_currency($amount) {
    return number_format($amount, 0) . ' Ks';
}

// Format Status Badge
function format_status_badge($status) {
    switch ($status) {
        case 'active':
            return '<span class="px-2 py-1 rounded text-xs font-bold bg-green-500/20 text-green-400 uppercase tracking-wider">Active</span>';
        case 'pending':
            return '<span class="px-2 py-1 rounded text-xs font-bold bg-yellow-500/20 text-yellow-400 uppercase tracking-wider">Pending</span>';
        case 'rejected':
            return '<span class="px-2 py-1 rounded text-xs font-bold bg-red-500/20 text-red-400 uppercase tracking-wider">Rejected</span>';
        default:
            return '<span class="px-2 py-1 rounded text-xs font-bold bg-gray-500/20 text-gray-400 uppercase tracking-wider">'.htmlspecialchars($status).'</span>';
    }
}
?>