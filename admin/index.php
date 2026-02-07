<?php
// admin/index.php

// 1. Include Header 
// This file initiates the session, checks authentication, connects to the DB,
// and renders the HTML <head> and Sidebar navigation.
require_once 'includes/header.php';

// 2. Determine View using Router Function
// The get_admin_view() function is defined in admin/includes/functions.php.
// It checks the $_GET['page'] parameter against a whitelist of allowed files.
$view_path = get_admin_view();

// 3. Render Content
if (file_exists($view_path)) {
    // Load the specific module (e.g., dashboard.php, orders.php)
    require_once $view_path;
} else {
    // 4. Fallback / 404 Error UI
    // If the file returned by the router doesn't physically exist on the server
    ?>
    <div class="flex flex-col items-center justify-center h-full text-slate-400 fade-in">
        <div class="bg-slate-800 p-10 rounded-2xl border border-slate-700 text-center shadow-2xl max-w-md relative overflow-hidden">
            <!-- Background Glow -->
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-red-500 via-yellow-500 to-red-500"></div>
            
            <div class="mb-6 relative">
                <div class="absolute inset-0 bg-red-500/20 blur-xl rounded-full"></div>
                <i class="fas fa-ghost text-6xl text-red-500 relative z-10 animate-bounce"></i>
            </div>
            
            <h2 class="text-3xl font-bold text-white mb-2 tracking-tight">404 Not Found</h2>
            <p class="text-sm text-slate-400 mb-8 leading-relaxed">
                The admin module you are looking for <br> does not exist or has been moved.
            </p>
            
            <a href="index.php" class="group bg-slate-700 hover:bg-blue-600 text-white px-8 py-3 rounded-xl font-bold transition-all duration-300 shadow-lg flex items-center justify-center gap-2">
                <i class="fas fa-tachometer-alt group-hover:-translate-x-1 transition"></i>
                Return to Dashboard
            </a>
        </div>
    </div>
    <?php
}

// 5. Include Footer
// Closes the main container and body tags
require_once 'includes/footer.php';
?>