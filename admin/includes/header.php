<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
check_admin_auth();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal - ScottSub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #0f172a; color: #e2e8f0; }
        .glass-nav { background: rgba(30, 41, 59, 0.9); backdrop-filter: blur(10px); border-right: 1px solid rgba(255, 255, 255, 0.05); }
        .nav-item:hover { background: rgba(255, 255, 255, 0.05); color: #fff; }
        .nav-item.active { background: rgba(59, 130, 246, 0.1); color: #60a5fa; border-right: 3px solid #60a5fa; }
    </style>
</head>
<body class="flex h-screen overflow-hidden">

    <!-- Sidebar -->
    <aside class="w-64 glass-nav flex flex-col h-full hidden md:flex">
        <div class="h-16 flex items-center px-6 border-b border-gray-700/50">
            <i class="fas fa-shield-alt text-red-500 text-xl mr-3"></i>
            <span class="font-bold text-lg tracking-wide">ScottAdmin</span>
        </div>

        <nav class="flex-1 py-6 space-y-1 overflow-y-auto">
            <p class="px-6 text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Main</p>
            <a href="index.php" class="nav-item flex items-center px-6 py-3 text-sm font-medium transition <?php echo basename($_SERVER['PHP_SELF'])=='index.php'?'active':'text-gray-400'; ?>">
                <i class="fas fa-chart-pie w-5 mr-3"></i> Dashboard
            </a>
            <a href="reports.php" class="nav-item flex items-center px-6 py-3 text-sm font-medium transition <?php echo basename($_SERVER['PHP_SELF'])=='reports.php'?'active':'text-gray-400'; ?>">
                <i class="fas fa-chart-line w-5 mr-3"></i> Reports & Expenses
            </a>

            <p class="px-6 text-xs font-bold text-gray-500 uppercase tracking-wider mb-2 mt-6">Management</p>
            <a href="orders.php" class="nav-item flex items-center px-6 py-3 text-sm font-medium transition text-gray-400">
                <i class="fas fa-shopping-cart w-5 mr-3"></i> Orders
            </a>
            <a href="products.php" class="nav-item flex items-center px-6 py-3 text-sm font-medium transition text-gray-400">
                <i class="fas fa-box w-5 mr-3"></i> Products
            </a>
            <a href="banners.php" class="nav-item flex items-center px-6 py-3 text-sm font-medium transition text-gray-400">
                <i class="fas fa-images w-5 mr-3"></i> Banners
            </a>
            <a href="users.php" class="nav-item flex items-center px-6 py-3 text-sm font-medium transition text-gray-400">
                <i class="fas fa-users w-5 mr-3"></i> Users
            </a>
        </nav>

        <div class="p-4 border-t border-gray-700/50">
            <a href="logout.php" class="flex items-center px-4 py-2 text-sm font-medium text-red-400 hover:text-red-300 hover:bg-red-900/20 rounded transition">
                <i class="fas fa-sign-out-alt w-5 mr-3"></i> Logout
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col h-full relative overflow-hidden">
        <!-- Top Mobile Header -->
        <header class="h-16 bg-slate-800/50 backdrop-blur border-b border-gray-700/50 flex items-center justify-between px-6 md:hidden">
            <span class="font-bold">Admin Portal</span>
            <button onclick="document.querySelector('aside').classList.toggle('hidden'); document.querySelector('aside').classList.toggle('absolute'); document.querySelector('aside').classList.toggle('z-50');" class="text-gray-300"><i class="fas fa-bars"></i></button>
        </header>

        <!-- Scrollable Content -->
        <main class="flex-1 overflow-y-auto p-6 md:p-10">