<?php
// index.php

// 1. Load Core System
require_once 'includes/config.php';
require_once 'includes/functions.php';

// 2. Get Request Params
$module = isset($_GET['module']) ? $_GET['module'] : 'home';
$page   = isset($_GET['page'])   ? $_GET['page']   : 'index';

// 3. Define Allowed Routes (Whitelist)
// This strictly controls which files can be loaded for security.
$allowed_routes = [
    // Landing & Dashboard
    'home' => ['index'],
    
    // Authentication
    'auth' => ['login', 'register', 'logout'],
    
    // Shopping Logic
    'shop' => ['category', 'checkout', 'search' , 'product'],
    
    // User Account
    'user' => ['orders', 'agent', 'profile' , 'invoice' , 'wishlist' , 'dashboard'],
    
    // Information Pages
    'info' => ['support', 'terms', 'privacy', 'tutorial'],
    
    // Error Pages
    'error' => ['404']
];

// 4. Validate Route
if (!array_key_exists($module, $allowed_routes) || !in_array($page, $allowed_routes[$module])) {
    // Force 404 if route doesn't exist
    $module = 'error';
    $page = '404';
}

// 5. Auth Middleware (Protect specific pages)
// These pages require the user to be logged in.
$protected_pages = [
    'user' => ['orders', 'agent', 'profile'],
    'shop' => ['checkout']
];

if (isset($protected_pages[$module]) && in_array($page, $protected_pages[$module])) {
    if (!is_logged_in()) {
        // Redirect to login with "redirect" param to return here after login
        $current_url = urlencode("index.php?module=$module&page=$page" . (isset($_GET['id']) ? "&id=".$_GET['id'] : ""));
        redirect("index.php?module=auth&page=login&redirect=$current_url");
    }
}

// 6. Output Buffering (To capture header/footer logic cleanly)
ob_start();

// Include Header (Skip for Auth pages to allow custom layouts)
if ($module !== 'auth' && $module !== 'error') {
    include 'includes/header.php';
}

// 7. Load the Module File
$file_path = "modules/{$module}/{$page}.php";

if (file_exists($file_path)) {
    include $file_path;
} else {
    // Fallback if file is missing despite being in whitelist
    include 'modules/error/404.php';
}

// Include Footer
if ($module !== 'auth' && $module !== 'error') {
    include 'includes/footer.php';
}

ob_end_flush();
?>