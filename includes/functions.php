<?php
// includes/functions.php

/**
 * --------------------------------------------------------------------------
 * CONFIGURATION CONSTANTS (If not loaded via config.php)
 * --------------------------------------------------------------------------
 */
if (!defined('TG_BOT_TOKEN')) {
    // REPLACE WITH YOUR ACTUAL BOT TOKEN & CHAT ID
    define('TG_BOT_TOKEN', '8394551492:AAEC3JtdKSHDHrvKApZcIhI9Jwd14bpDayY'); 
    define('TG_ADMIN_CHAT_ID', '8125603481'); 
}

// if (!defined('EXCHANGE_RATE')) {
//     define('EXCHANGE_RATE', 4200); // 1 USD = 4200 MMK
// }

// if (!defined('BASE_URL')) {
//     define('BASE_URL', 'http://localhost/scottsub/');
// }

/**
 * --------------------------------------------------------------------------
 * SECURITY & AUTHENTICATION
 * --------------------------------------------------------------------------
 */

// Generate CSRF Token for Forms
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Check if User is Logged In
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Safe Redirect Helper
function redirect($url) {
    if (!headers_sent()) {
        header("Location: " . BASE_URL . $url);
    } else {
        echo "<script>window.location.href='" . BASE_URL . $url . "';</script>";
    }
    exit;
}

/**
 * --------------------------------------------------------------------------
 * PRICING & DISCOUNTS
 * --------------------------------------------------------------------------
 */

// Get Active Agent Discount % for a User
function get_user_discount($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT p.discount_percent FROM user_passes up
            JOIN passes p ON up.pass_id = p.id
            WHERE up.user_id = ? AND up.status = 'active' AND up.expires_at > NOW()
            ORDER BY p.discount_percent DESC LIMIT 1
        ");
        $stmt->execute([$user_id]);
        return (int) $stmt->fetchColumn() ?: 0;
    } catch (PDOException $e) {
        return 0;
    }
}

// Format Price based on Session Currency (MMK or USD)
function format_price($amount_mmk) {
    // Check Session Currency
    if (isset($_SESSION['currency']) && $_SESSION['currency'] === 'USD') {
        $usd = $amount_mmk / EXCHANGE_RATE;
        return '$' . number_format($usd, 2);
    }
    
    // Default to MMK
    return number_format($amount_mmk, 0) . ' Ks';
}

/**
 * --------------------------------------------------------------------------
 * TELEGRAM NOTIFICATIONS (WEBHOOK / API)
 * --------------------------------------------------------------------------
 */

function send_telegram_alert($order_id, $product_name, $price, $username) {
    $token = TG_BOT_TOKEN;
    $chat_id = TG_ADMIN_CHAT_ID;
    
    // Construct Admin Link
    $admin_url = BASE_URL . "admin/order_detail.php?id=" . $order_id;
    
    // Build Message
    $message = "🚨 <b>New Order Received!</b>\n\n";
    $message .= "🆔 <b>Order ID:</b> #{$order_id}\n";
    $message .= "👤 <b>User:</b> " . htmlspecialchars($username) . "\n";
    $message .= "📦 <b>Item:</b> " . htmlspecialchars($product_name) . "\n";
    $message .= "💰 <b>Paid:</b> " . number_format($price) . " Ks\n";
    $message .= "\n👇 <a href='{$admin_url}'>Click to Process Order</a>";

    // Telegram API Endpoint
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    
    $data = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];

    // Send Request via CURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL check for localhost
    $result = curl_exec($ch);
    
    // Log error if needed
    if(curl_errno($ch)){
        error_log('Telegram Curl Error: ' . curl_error($ch));
    }
    
    curl_close($ch);
    
    return $result;
}

/**
 * --------------------------------------------------------------------------
 * UTILITIES
 * --------------------------------------------------------------------------
 */

// Get Unread Admin Messages Count
function get_unread_notifications($user_id) {
    global $pdo;
    try {
        // Count unread messages from Admin in active orders
        // Note: Ideally, add a 'read_at' column to order_messages table for accuracy
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM order_messages om
            JOIN orders o ON om.order_id = o.id
            WHERE o.user_id = ? AND om.sender_type = 'admin' 
            AND om.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute([$user_id]);
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

// Sanitize Output
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
?>