<?php
// admin/includes/header.php

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Secure the admin panel
check_admin_auth();

// Get current page for active state highlighting
$current_page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - DigitalMarketplaceMM</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #0f172a; /* Slate 900 */
            color: #e2e8f0;            /* Slate 200 */
        }
        
        /* Glassmorphism Sidebar */
        .glass-nav { 
            background: rgba(30, 41, 59, 0.95); 
            backdrop-filter: blur(12px); 
            border-right: 1px solid rgba(255, 255, 255, 0.05); 
        }
        
        /* Navigation Item Styles */
        .nav-item {
            transition: all 0.2s ease-in-out;
            border-left: 3px solid transparent;
        }
        .nav-item:hover { 
            background: rgba(255, 255, 255, 0.03); 
            color: #fff; 
        }
        .nav-item.active { 
            background: rgba(59, 130, 246, 0.1); /* Blue tint */
            color: #60a5fa; /* Blue 400 */
            border-left: 3px solid #60a5fa; 
        }
        
        /* Custom Scrollbar */
        .custom-scrollbar::-webkit-scrollbar { width: 5px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #1e293b; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #475569; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #64748b; }
    </style>
</head>
<body class="flex h-screen overflow-hidden">

    <!-- Sidebar Navigation -->
    <aside class="w-64 glass-nav flex flex-col h-full hidden md:flex transition-all duration-300 z-30" id="sidebar">
        
        <!-- Brand Logo -->
        <div class="h-16 flex items-center px-6 border-b border-gray-700/50 shrink-0">
            <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center shadow-lg shadow-blue-500/20 mr-3">
                <i class="fas fa-shield-alt text-white text-sm"></i>
            </div>
            <span class="font-bold text-lg tracking-wide text-white">DMMM Admin</span>
        </div>

        <!-- Navigation Links -->
        <nav class="flex-1 py-6 space-y-1 overflow-y-auto custom-scrollbar">
            
            <!-- Section: Overview -->
            <div class="px-6 mb-2">
                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">Overview</span>
            </div>
            
            <a href="<?php echo admin_url('dashboard'); ?>" class="nav-item flex items-center px-6 py-3 text-sm font-medium text-gray-400 <?php echo $current_page=='dashboard'?'active':''; ?>">
                <i class="fas fa-chart-pie w-5 mr-3 text-center"></i> Dashboard
            </a>
            
            <a href="<?php echo admin_url('reports'); ?>" class="nav-item flex items-center px-6 py-3 text-sm font-medium text-gray-400 <?php echo $current_page=='reports'?'active':''; ?>">
                <i class="fas fa-chart-line w-5 mr-3 text-center"></i> Reports & Expenses
            </a>

            <!-- Section: Management -->
            <div class="px-6 mb-2 mt-6">
                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">Store Management</span>
            </div>
            
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

            <a href="<?php echo admin_url('keys'); ?>" class="nav-item flex items-center px-6 py-3 text-sm font-medium text-gray-400 <?php echo $current_page=='keys'?'active':''; ?>">
                <i class="fas fa-key w-5 mr-3 text-center"></i> Stock / Keys
            </a>
            
            <a href="<?php echo admin_url('banners'); ?>" class="nav-item flex items-center px-6 py-3 text-sm font-medium text-gray-400 <?php echo $current_page=='banners'?'active':''; ?>">
                <i class="fas fa-images w-5 mr-3 text-center"></i> Banners / Ads
            </a>
            
            <a href="<?php echo admin_url('payments'); ?>" class="nav-item flex items-center px-6 py-3 text-sm font-medium text-gray-400 <?php echo $current_page=='payments'?'active':''; ?>">
                <i class="fas fa-wallet w-5 mr-3 text-center"></i> Payment Methods
            </a>

            <a href="<?php echo admin_url('users'); ?>" class="nav-item flex items-center px-6 py-3 text-sm font-medium text-gray-400 <?php echo $current_page=='users'?'active':''; ?>">
                <i class="fas fa-users w-5 mr-3 text-center"></i> Customers
            </a>

            <!-- Section: System -->
            <div class="px-6 mb-2 mt-6">
                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">System</span>
            </div>
            
            <a href="<?php echo admin_url('settings'); ?>" class="nav-item flex items-center px-6 py-3 text-sm font-medium text-gray-400 <?php echo $current_page=='settings'?'active':''; ?>">
                <i class="fas fa-cog w-5 mr-3 text-center"></i> Settings
            </a>
        </nav>

        <!-- User Profile / Logout -->
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
        
        <!-- Mobile Header Toggle -->
        <header class="h-16 bg-slate-800/80 backdrop-blur border-b border-gray-700/50 flex items-center justify-between px-6 md:hidden z-20 shrink-0">
            <div class="flex items-center gap-2">
                <i class="fas fa-shield-alt text-blue-500"></i>
                <span class="font-bold text-white">Admin Panel</span>
            </div>
            <button onclick="toggleSidebar()" class="text-gray-300 p-2 hover:text-white transition">
                <i class="fas fa-bars text-xl"></i>
            </button>
        </header>

        <!-- Scrollable Page Content -->
        <main class="flex-1 overflow-y-auto p-6 md:p-10 custom-scrollbar relative">
            
            <!-- Mobile Sidebar Script -->
            <script>
                function toggleSidebar() {
                    const sidebar = document.getElementById('sidebar');
                    sidebar.classList.toggle('hidden');
                    sidebar.classList.toggle('absolute');
                    sidebar.classList.toggle('inset-0');
                    sidebar.classList.toggle('w-full'); // Full width on mobile
                    sidebar.classList.toggle('bg-slate-900');
                }
            </script>