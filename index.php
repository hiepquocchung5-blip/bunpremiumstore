<?php
// index.php
// PRODUCTION ROUTER v1.2 - AJAX Support & New Routes added

/**
 * --------------------------------------------------------------------------
 * 1. INITIALIZATION
 * --------------------------------------------------------------------------
 */

// Load Configuration & Core Functions
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Get Request Parameters (Default to Home)
$module = isset($_GET['module']) ? $_GET['module'] : 'home';
$page   = isset($_GET['page'])   ? $_GET['page']   : 'index';
$page_title = 'DigitalMM | Premium Digital Marketplace';
$page_description = 'Premium digital marketplace for games, software, passes, and instant delivery products.';
$page_type = 'website';
$page_image = defined('BASE_URL') ? BASE_URL . 'assets/images/og-image.png' : 'assets/images/og-image.png';
$page_canonical = defined('BASE_URL') ? BASE_URL . 'index.php' : 'index.php';

/**
 * --------------------------------------------------------------------------
 * 2. ROUTE WHITELIST (SECURITY)
 * Defines exactly which files are allowed to be loaded.
 * --------------------------------------------------------------------------
 */
$allowed_routes = [
    // Landing & Dashboard
    'home' => ['index'],
    
    // Authentication & Security
    'auth' => [
        'login', 
        'register', 
        'logout', 
        'verify', 
        'verify_resend',
        'forgot_password',
        'reset_password'
    ],
    
    // Shopping & Products
    'shop' => [
        'category', 
        'product', 
        'checkout', 
        'search',
        'blindbox'
    ],
    
    // User Account Management
    'user' => [
        'dashboard',
        'orders', 
        'profile', 
        'agent', 
        'wishlist',
        'wallet',           // Added Wallet
        'referrals',        // Added Referrals
        'invoice'
    ],
    
    // Static Information
    'info' => [
        'support', 
        'terms', 
        'privacy', 
        'tutorial'
    ],
    
    // System
    'error' => ['404']
];

// Validate Request against Whitelist
if (!array_key_exists($module, $allowed_routes) || !in_array($page, $allowed_routes[$module])) {
    // Force 404 if route doesn't exist
    $module = 'error';
    $page = '404';
}

if ($module === 'home' && $page === 'index') {
    $page_title = 'DigitalMM | Fresh Digital Deals';
    $page_description = 'Browse fresh digital products, curated picks, and instant delivery deals built for fast checkout.';
} elseif ($module === 'shop' && $page === 'category') {
    $cat_id_meta = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $page_title = $cat_id_meta > 0 ? 'DigitalMM | Category View' : 'DigitalMM | All Products';
    $page_description = 'Explore digital products with clean filters, fast browsing, and reliable delivery.';
    if ($cat_id_meta > 0) {
        $stmt = $pdo->prepare("SELECT name, description, image_url FROM categories WHERE id = ?");
        $stmt->execute([$cat_id_meta]);
        if ($cat = $stmt->fetch()) {
            $page_title = 'DigitalMM | ' . $cat['name'];
            $page_description = $cat['description'] ?: $page_description;
            if (!empty($cat['image_url'])) {
                $page_image = defined('BASE_URL') ? BASE_URL . $cat['image_url'] : $cat['image_url'];
            }
        }
    }
} elseif ($module === 'shop' && $page === 'product') {
    $product_meta_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($product_meta_id > 0) {
        $stmt = $pdo->prepare("
            SELECT p.name, p.description, p.image_path, c.name as cat_name
            FROM products p
            JOIN categories c ON p.category_id = c.id
            WHERE p.id = ?
        ");
        $stmt->execute([$product_meta_id]);
        if ($product_meta = $stmt->fetch()) {
            $page_title = 'DigitalMM | ' . $product_meta['name'];
            $page_description = $product_meta['description'] ?: ('Buy ' . $product_meta['name'] . ' from the ' . $product_meta['cat_name'] . ' collection.');
            if (!empty($product_meta['image_path'])) {
                $page_image = defined('BASE_URL') ? BASE_URL . $product_meta['image_path'] : $product_meta['image_path'];
            }
            $page_type = 'product';
            $page_canonical = defined('BASE_URL') ? BASE_URL . 'index.php?module=shop&page=product&id=' . $product_meta_id : 'index.php?module=shop&page=product&id=' . $product_meta_id;
        }
    }
} elseif ($module === 'shop' && $page === 'search') {
    $q = trim($_GET['q'] ?? '');
    $page_title = $q !== '' ? 'DigitalMM | Search: ' . $q : 'DigitalMM | Search';
    $page_description = 'Search digital goods, software, and instant delivery products in one clean interface.';
}

/**
 * --------------------------------------------------------------------------
 * 3. MIDDLEWARE (AUTH GUARD)
 * Protects pages that require login.
 * --------------------------------------------------------------------------
 */
$protected_pages = [
    'user' => ['dashboard', 'orders', 'profile', 'agent', 'wishlist', 'wallet', 'referrals', 'invoice'],
    'shop' => ['checkout']
];

if (isset($protected_pages[$module]) && in_array($page, $protected_pages[$module])) {
    if (!is_logged_in()) {
        // Construct return URL so user is sent back after login
        $current_url = "index.php?module=$module&page=$page";
        if (!empty($_GET['id'])) $current_url .= "&id=" . (int)$_GET['id'];
        
        redirect("index.php?module=auth&page=login&redirect=" . urlencode($current_url));
    }
}

/**
 * --------------------------------------------------------------------------
 * 4. VIEW RENDERING
 * Handles Layouts (Header/Footer) vs Standalone Pages
 * --------------------------------------------------------------------------
 */

// Define Standalone Pages (No Header/Footer)
$standalone_views = [
    'auth' => ['login', 'register', 'verify', 'verify_resend', 'forgot_password', 'reset_password'],
    'user' => ['invoice'],
    'error' => ['404'] 
];

$is_standalone = (isset($standalone_views[$module]) && in_array($page, $standalone_views[$module]));

// FIX: If this is an AJAX request (like the Live Chat polling), do NOT load the header/footer
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    $is_standalone = true;
}

// Buffer Output to prevent header errors
ob_start();

// A. Load Header (If not standalone)
if (!$is_standalone) {
    include 'includes/header.php';
}

// B. Load Module Content
$file_path = "modules/{$module}/{$page}.php";

if (file_exists($file_path)) {
    include $file_path;
} else {
    // Fallback if file missing physically
    include 'modules/error/404.php';
}

// C. Load Footer (If not standalone)
if (!$is_standalone) {
    include 'includes/footer.php';
}

ob_end_flush();
?>
