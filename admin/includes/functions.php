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
    
    // Whitelist of allowed pages => filenameS
    $routes = [
        'dashboard'    => 'dashboard.php',
        'orders'       => 'orders.php',
        'order_detail' => 'order_detail.php',
        'products'     => 'products.php',
        'product_edit' => 'product_edit.php',
        'categories'   => 'categories.php',
        'category_edit'=> 'category_edit.php',
        'keys'         => 'keys.php',
        'users'        => 'users.php',
        'user_detail'  => 'user_detail.php',
        'reviews'      => 'reviews.php',
        'reports'      => 'reports.php',
        'banners'      => 'banners.php',
        'banner_edit'  => 'banner_edit.php',   // NEW
        'payments'     => 'payments.php',
        'payment_edit' => 'payment_edit.php',  // NEW
        'settings'     => 'settings.php',
        'passes'       => 'passes.php', 
        'admins'       => 'admins.php',
        'pandl'         => 'pandl.php',
        'coupons'       => 'coupons.php',
        'notifications'=> 'notifications.php', 
        'blindboxes'      => 'blindboxes.php'
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
function get_total_revenue($pdo, $start_date = null, $end_date = null) {
    try {
        if ($start_date && $end_date) {
            $stmt = $pdo->prepare("SELECT SUM(total_price_paid) FROM orders WHERE status = 'active' AND created_at BETWEEN ? AND ?");
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
        } else {
            $stmt = $pdo->query("SELECT SUM(total_price_paid) FROM orders WHERE status = 'active'");
        }
        return (float) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0.00;
    }
}

// Get Total Expenses
function get_total_expenses($pdo, $start_date = null, $end_date = null) {
    try {
        if ($start_date && $end_date) {
            $stmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE created_at BETWEEN ? AND ?");
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
        } else {
            $stmt = $pdo->query("SELECT SUM(amount) FROM expenses");
        }
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
    $base_classes = "px-3 py-1.5 rounded-xl text-[10px] font-bold uppercase tracking-widest border shadow-sm";
    switch ($status) {
        case 'active':
        case 'completed':
        case 'success':
            return '<span class="'.$base_classes.' bg-emerald-500/10 text-emerald-400 border-emerald-500/20">Active</span>';
        case 'pending':
        case 'processing':
            return '<span class="'.$base_classes.' bg-amber-500/10 text-amber-400 border-amber-500/20">Pending</span>';
        case 'rejected':
        case 'cancelled':
        case 'failed':
            return '<span class="'.$base_classes.' bg-rose-500/10 text-rose-400 border-rose-500/20">Rejected</span>';
        case 'closed':
        case 'archived':
            return '<span class="'.$base_classes.' bg-slate-500/10 text-slate-400 border-slate-500/20">Closed</span>';
        default:
            return '<span class="'.$base_classes.' bg-indigo-500/10 text-indigo-400 border-indigo-500/20">'.htmlspecialchars($status).'</span>';
    }
}

/**
 * Optimizes an image file in-place (supports JPEG, PNG, WebP)
 * to save disk space and improve loading times.
 */
function optimize_image_in_place($file_path, $quality = 80) {
    if (!extension_loaded('gd')) return false;
    $info = @getimagesize($file_path);
    if ($info === false) return false;
    
    $mime = $info['mime'];
    switch ($mime) {
        case 'image/jpeg':
            $image = @imagecreatefromjpeg($file_path);
            if ($image) {
                @imagejpeg($image, $file_path, $quality);
                imagedestroy($image);
                return true;
            }
            break;
        case 'image/png':
            $image = @imagecreatefrompng($file_path);
            if ($image) {
                // png compression is 0 (no compression) to 9
                $png_quality = 9 - round(($quality / 100) * 9);
                @imagepng($image, $file_path, $png_quality);
                imagedestroy($image);
                return true;
            }
            break;
        case 'image/webp':
            $image = @imagecreatefromwebp($file_path);
            if ($image) {
                @imagewebp($image, $file_path, $quality);
                imagedestroy($image);
                return true;
            }
            break;
    }
    return false;
}
?>
