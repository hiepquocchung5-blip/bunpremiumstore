<?php
// admin/includes/header.php

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

check_admin_auth();

$current_page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - DigitalMarketplaceMM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #0f172a; color: #e2e8f0; }
        .glass-nav { background: rgba(30, 41, 59, 0.95); backdrop-filter: blur(12px); border-right: 1px solid rgba(255, 255, 255, 0.05); }
        .nav-item { transition: all 0.2s ease; }
        .nav-item:hover { background: rgba(255, 255, 255, 0.03); color: #fff; }
        .nav-item.active { background: rgba(59, 130, 246, 0.1); color: #60a5fa; border-left: 3px solid #60a5fa; }
        .custom-scrollbar::-webkit-scrollbar { width: 5px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #1e293b; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #475569; border-radius: 4px; }
    </style>
</head>
<body class="flex h-screen overflow-hidden">

    <!-- Mobile Overlay -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 hidden opacity-0 transition-opacity duration-300 md:hidden" onclick="toggleSidebar()"></div>

    <!-- Sidebar Navigation -->
    <aside class="fixed inset-y-0 left-0 z-50 w-64 glass-nav flex flex-col h-full transform -translate-x-full md:relative md:translate-x-0 transition-transform duration-300 ease-in-out" id="sidebar">
        <div class="h-16 flex items-center justify-between px-6 border-b border-gray-700/50 shrink-0">
            <div class="flex items-center">
                <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center shadow-lg shadow-blue-500/20 mr-3">
                    <i class="fas fa-shield-alt text-white text-sm"></i>
                </div>
                <span class="font-bold text-lg tracking-wide text-white">DMMM Admin</span>
            </div>
            <!-- Mobile Close Button -->
            <button onclick="toggleSidebar()" class="md:hidden text-gray-400 hover:text-white transition">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <nav class="flex-1 py-6 space-y-1 overflow-y-auto custom-scrollbar">
            <p class="px-6 text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Overview</p>
            <a href="<?php echo admin_url('dashboard'); ?>" class="nav-item flex items-center px-6 py-3 text-sm font-medium text-gray-400 <?php echo $current_page=='dashboard'?'active':''; ?>">
                <i class="fas fa-chart-pie w-5 mr-3 text-center"></i> Dashboard
            </a>
            <a href="<?php echo admin_url('reports'); ?>" class="nav-item flex items-center px-6 py-3 text-sm font-medium text-gray-400 <?php echo $current_page=='reports'?'active':''; ?>">
                <i class="fas fa-chart-line w-5 mr-3 text-center"></i> Reports & Expenses
            </a>

            <p class="px-6 text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 mt-6">Management</p>
            <a href="<?php echo admin_url('orders'); ?>" class="nav-item flex items-center px-6 py-3 text-sm font-medium text-gray-400 <?php echo $current_page=='orders'?'active':''; ?>">
                <i class="fas fa-shopping-cart w-5 mr-3 text-center"></i> Orders
                <?php 
                    $pending = get_pending_count($pdo);
                    if($pending > 0) echo "<span class='ml-auto bg-yellow-500 text-black text-[10px] font-bold px-2 py-0.5 rounded-full'>$pending</span>";
                ?>
            </a>
            <a href="<?php echo admin_url('products'); ?>" class="nav-item flex items-center px-6 py-3 text-sm font-medium text-gray-400 <?php echo $current_page=='products'?'active':''; ?>">
                <i class="fas fa-box w-5 mr-3 text-center"></i> Products
            </a>
            <a href="<?php echo admin_url('categories'); ?>" class="nav-item flex items-center px-6 py-3 text-sm font-medium text-gray-400 <?php echo $current_page=='categories'?'active':''; ?>">
                <i class="fas fa-folder w-5 mr-3 text-center"></i> Categories
            </a>
            <a href="<?php echo admin_url('keys'); ?>" class="nav-item flex items-center px-6 py-3 text-sm font-medium text-gray-400 <?php echo $current_page=='keys'?'active':''; ?>">
                <i class="fas fa-key w-5 mr-3 text-center"></i> Stock / Keys
            </a>
            <a href="<?php echo admin_url('banners'); ?>" class="nav-item flex items-center px-6 py-3 text-sm font-medium text-gray-400 <?php echo $current_page=='banners'?'active':''; ?>">
                <i class="fas fa-images w-5 mr-3 text-center"></i> Banners
            </a>
            <a href="<?php echo admin_url('payments'); ?>" class="nav-item flex items-center px-6 py-3 text-sm font-medium text-gray-400 <?php echo $current_page=='payments'?'active':''; ?>">
                <i class="fas fa-wallet w-5 mr-3 text-center"></i> Payment Methods
            </a>
            <a href="<?php echo admin_url('users'); ?>" class="nav-item flex items-center px-6 py-3 text-sm font-medium text-gray-400 <?php echo $current_page=='users'?'active':''; ?>">
                <i class="fas fa-users w-5 mr-3 text-center"></i> Customers
            </a>
            <a href="<?php echo admin_url('reviews'); ?>" class="nav-item flex items-center px-6 py-3 text-sm font-medium text-gray-400 <?php echo $current_page=='reviews'?'active':''; ?>">
                <i class="fas fa-star w-5 mr-3 text-center"></i> Reviews
            </a>

            <p class="px-6 text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 mt-6">System</p>
            <a href="<?php echo admin_url('admins'); ?>" class="nav-item flex items-center px-6 py-3 text-sm font-medium text-gray-400 <?php echo $current_page=='admins'?'active':''; ?>">
                <i class="fas fa-user-shield w-5 mr-3 text-center"></i> Staff & Admins
            </a>
            <a href="<?php echo admin_url('settings'); ?>" class="nav-item flex items-center px-6 py-3 text-sm font-medium text-gray-400 <?php echo $current_page=='settings'?'active':''; ?>">
                <i class="fas fa-cog w-5 mr-3 text-center"></i> Settings
            </a>
        </nav>

        <div class="p-4 border-t border-gray-700/50 bg-slate-900/50 shrink-0">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center text-xs font-bold text-slate-300">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="text-xs">
                        <p class="text-white font-medium">Administrator</p>
                        <p class="text-gray-500">Logged in</p>
                    </div>
                </div>
                <a href="logout.php" class="text-gray-400 hover:text-red-400 transition p-2 rounded-lg hover:bg-slate-800" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content Wrapper -->
    <div class="flex-1 flex flex-col h-full relative overflow-hidden bg-slate-900">
        
        <!-- Mobile Top Header -->
        <header class="h-16 bg-slate-800/80 backdrop-blur border-b border-gray-700/50 flex items-center justify-between px-6 md:hidden z-20 shrink-0 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center shadow-lg shadow-blue-500/20">
                    <i class="fas fa-shield-alt text-white text-sm"></i>
                </div>
                <span class="font-bold text-white tracking-wide">Admin Portal</span>
            </div>
            <button onclick="toggleSidebar()" class="text-gray-300 hover:text-white p-2 transition rounded-lg hover:bg-slate-700/50 focus:outline-none">
                <i class="fas fa-bars text-xl"></i>
            </button>
        </header>

        <!-- Dynamic Content Area -->
        <main class="flex-1 overflow-y-auto p-4 sm:p-6 md:p-10 custom-scrollbar relative">
            
        <script>
            // Sidebar Mobile Toggle Script
            function toggleSidebar() {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebar-overlay');
                
                if (sidebar.classList.contains('-translate-x-full')) {
                    // Open Sidebar
                    sidebar.classList.remove('-translate-x-full');
                    overlay.classList.remove('hidden');
                    // Small delay to allow display:block to apply before animating opacity
                    setTimeout(() => overlay.classList.remove('opacity-0'), 10);
                } else {
                    // Close Sidebar
                    sidebar.classList.add('-translate-x-full');
                    overlay.classList.add('opacity-0');
                    // Wait for transition to finish before hiding
                    setTimeout(() => overlay.classList.add('hidden'), 300);
                }
            }
        </script>