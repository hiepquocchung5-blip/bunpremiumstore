<?php
// index.php
// PRODUCTION ROUTER v1.0

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
        'verify',           // New
        'verify_resend',    // New
        'forgot_password',  // New
        'reset_password'    // New
    ],
    
    // Shopping & Products
    'shop' => [
        'category', 
        'product',          // New (Details)
        'checkout', 
        'search'            // New
    ],
    
    // User Account Management
    'user' => [
        'dashboard',        // New (Hub)
        'orders', 
        'profile', 
        'agent', 
        'wishlist',         // New
        'invoice'           // New (Printable)
    ],
    
    // Static Information
    'info' => [
        'support', 
        'terms', 
        'privacy', 
        'tutorial'          // New
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

/**
 * --------------------------------------------------------------------------
 * 3. MIDDLEWARE (AUTH GUARD)
 * Protects pages that require login.
 * --------------------------------------------------------------------------
 */
$protected_pages = [
    'user' => ['dashboard', 'orders', 'profile', 'agent', 'wishlist', 'invoice'],
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
// Auth pages have their own glass layout. Invoice is printable.
$standalone_views = [
    'auth' => ['login', 'register', 'verify', 'verify_resend', 'forgot_password', 'reset_password'],
    'user' => ['invoice'],
    'error' => ['404'] // Optional: You might want header on 404, but keeping clean for now
];

$is_standalone = (isset($standalone_views[$module]) && in_array($page, $standalone_views[$module]));

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