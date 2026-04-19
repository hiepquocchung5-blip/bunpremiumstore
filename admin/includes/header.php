<?php
// admin/includes/header.php
// PRODUCTION v3.0 - Circuit Chaos Admin UI & Navigation Matrix

// 1. Secure Session & Auth Check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// 2. Helper Functions
function is_active($page_name, $current_page) {
    return $page_name === $current_page;
}

function admin_url($page, $params = []) {
    $url = "index.php?page=" . $page;
    foreach ($params as $key => $value) {
        $url .= "&" . urlencode($key) . "=" . urlencode($value);
    }
    return $url;
}

$current_page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$admin_role = $_SESSION['admin_role'] ?? 'support';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matrix Command - Admin Portal</title>
    
    <!-- Dependencies -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
    
    <style>
        body { 
            background-color: #020617; 
            color: #f8fafc; 
            font-family: 'Inter', sans-serif; 
        }
        
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(51, 65, 85, 0.8); border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #00f0ff; }
        
        .nav-item { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .nav-item:hover { background: rgba(0, 240, 255, 0.1); border-color: rgba(0, 240, 255, 0.5); color: #fff; }
        .nav-item.active { 
            background: linear-gradient(90deg, rgba(0, 240, 255, 0.15) 0%, transparent 100%);
            border-left-color: #00f0ff; 
            color: #00f0ff;
            text-shadow: 0 0 10px rgba(0, 240, 255, 0.5);
        }

        /* Ambient Glow Animations */
        @keyframes pulse-slow {
            0%, 100% { opacity: 0.3; }
            50% { opacity: 0.6; }
        }
        .animate-pulse-slow { animation: pulse-slow 4s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
    </style>

    <!-- Global App Configuration (Failsafe for Push APIs) -->
    <script>
        window.AppConfig = {
            vapidPublicKey: "<?php echo $_ENV['VAPID_PUBLIC_KEY'] ?? ''; ?>",
            baseUrl: "<?php echo defined('MAIN_SITE_URL') ? MAIN_SITE_URL : '/'; ?>"
        };
    </script>
</head>
<body class="flex h-screen overflow-hidden selection:bg-[#00f0ff]/30 selection:text-[#00f0ff]">

    <!-- Ambient Background -->
    <div class="fixed inset-0 pointer-events-none z-0">
        <div class="absolute top-0 left-0 w-96 h-96 bg-blue-600/10 rounded-full blur-[100px] animate-pulse-slow"></div>
        <div class="absolute bottom-0 right-0 w-96 h-96 bg-purple-600/10 rounded-full blur-[100px] animate-pulse-slow" style="animation-delay: 2s;"></div>
    </div>

    <!-- ========================================== -->
    <!-- MOBILE OVERLAY                             -->
    <!-- ========================================== -->
    <div id="mobileOverlay" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-40 hidden lg:hidden transition-opacity" onclick="toggleSidebar()"></div>

    <!-- ========================================== -->
    <!-- SIDEBAR NAVIGATION                         -->
    <!-- ========================================== -->
    <aside id="sidebar" class="fixed lg:static inset-y-0 left-0 z-50 w-72 bg-slate-900/90 backdrop-blur-2xl border-r border-slate-700/50 flex flex-col transform -translate-x-full lg:translate-x-0 transition-transform duration-300 shadow-[20px_0_50px_rgba(0,0,0,0.5)]">
        
        <!-- Brand Header -->
        <div class="h-20 flex items-center px-6 border-b border-slate-700/50 shrink-0 bg-slate-900">
            <a href="index.php" class="flex items-center gap-3 group">
                <div class="w-10 h-10 rounded-xl bg-slate-800 border border-[#00f0ff]/30 flex items-center justify-center shadow-[0_0_15px_rgba(0,240,255,0.1)] group-hover:shadow-[0_0_20px_rgba(0,240,255,0.3)] transition">
                    <i class="fas fa-bolt text-[#00f0ff] text-xl group-hover:scale-110 transition-transform"></i>
                </div>
                <div class="flex flex-col">
                    <span class="text-white font-black text-lg tracking-tight uppercase leading-none">Matrix <span class="text-[#00f0ff]">Command</span></span>
                    <span class="text-[9px] text-slate-500 font-bold uppercase tracking-widest mt-0.5">Admin Terminal</span>
                </div>
            </a>
        </div>

        <!-- Navigation Links -->
        <nav class="flex-1 overflow-y-auto custom-scrollbar py-6">
            
            <div class="px-6 mb-2">
                <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest">Core Systems</span>
            </div>
            
            <ul class="space-y-1 px-3 mb-8">
                <li>
                    <a href="<?php echo admin_url('dashboard'); ?>" class="nav-item flex items-center px-4 py-3 rounded-xl text-sm font-bold border-l-4 <?php echo is_active('dashboard', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                        <i class="fas fa-chart-pie w-6 text-center text-lg <?php echo is_active('dashboard', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                        <span class="ml-2">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo admin_url('orders'); ?>" class="nav-item flex items-center px-4 py-3 rounded-xl text-sm font-bold border-l-4 <?php echo is_active('orders', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                        <i class="fas fa-shopping-cart w-6 text-center text-lg <?php echo is_active('orders', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                        <span class="ml-2">Orders & Comms</span>
                        <?php 
                            // Quick badge for pending orders
                            global $pdo;
                            $pending = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn() ?: 0;
                            if($pending > 0) echo "<span class='ml-auto bg-red-500/20 text-red-400 border border-red-500/30 text-[10px] px-2 py-0.5 rounded-md font-mono shadow-[0_0_10px_rgba(239,68,68,0.2)] animate-pulse'>$pending</span>";
                        ?>
                    </a>
                </li>
                <li>
                    <a href="<?php echo admin_url('users'); ?>" class="nav-item flex items-center px-4 py-3 rounded-xl text-sm font-bold border-l-4 <?php echo is_active('users', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                        <i class="fas fa-users w-6 text-center text-lg <?php echo is_active('users', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                        <span class="ml-2">Operatives (Users)</span>
                    </a>
                </li>
            </ul>

            <div class="px-6 mb-2">
                <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest">Inventory Matrix</span>
            </div>

            <ul class="space-y-1 px-3 mb-8">
                <li>
                    <a href="<?php echo admin_url('products'); ?>" class="nav-item flex items-center px-4 py-3 rounded-xl text-sm font-bold border-l-4 <?php echo is_active('products', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                        <i class="fas fa-box w-6 text-center text-lg <?php echo is_active('products', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                        <span class="ml-2">Digital Assets</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo admin_url('categories'); ?>" class="nav-item flex items-center px-4 py-3 rounded-xl text-sm font-bold border-l-4 <?php echo is_active('categories', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                        <i class="fas fa-network-wired w-6 text-center text-lg <?php echo is_active('categories', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                        <span class="ml-2">Sectors (Categories)</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo admin_url('keys'); ?>" class="nav-item flex items-center px-4 py-3 rounded-xl text-sm font-bold border-l-4 <?php echo is_active('keys', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                        <i class="fas fa-key w-6 text-center text-lg <?php echo is_active('keys', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                        <span class="ml-2">Stock & Keys</span>
                    </a>
                </li>
            </ul>

            <div class="px-6 mb-2">
                <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest">Marketing & Finance</span>
            </div>

            <ul class="space-y-1 px-3 mb-8">
                <li>
                    <a href="<?php echo admin_url('notifications'); ?>" class="nav-item flex items-center px-4 py-3 rounded-xl text-sm font-bold border-l-4 <?php echo is_active('notifications', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                        <i class="fas fa-satellite-dish w-6 text-center text-lg <?php echo is_active('notifications', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                        <span class="ml-2">Push Matrix</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo admin_url('coupons'); ?>" class="nav-item flex items-center px-4 py-3 rounded-xl text-sm font-bold border-l-4 <?php echo is_active('coupons', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                        <i class="fas fa-ticket-alt w-6 text-center text-lg <?php echo is_active('coupons', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                        <span class="ml-2">Promo Codes</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo admin_url('blindboxes'); ?>" class="nav-item flex items-center px-4 py-3 rounded-xl text-sm font-bold border-l-4 <?php echo is_active('blindboxes', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                        <i class="fas fa-cube w-6 text-center text-lg <?php echo is_active('blindboxes', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                        <span class="ml-2">Gacha Nodes</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo admin_url('passes'); ?>" class="nav-item flex items-center px-4 py-3 rounded-xl text-sm font-bold border-l-4 <?php echo is_active('passes', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                        <i class="fas fa-crown w-6 text-center text-lg <?php echo is_active('passes', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                        <span class="ml-2">Agent Tiers</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo admin_url('pandl'); ?>" class="nav-item flex items-center px-4 py-3 rounded-xl text-sm font-bold border-l-4 <?php echo is_active('pandl', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                        <i class="fas fa-chart-line w-6 text-center text-lg <?php echo is_active('pandl', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                        <span class="ml-2">P&L Telemetry</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo admin_url('reports'); ?>" class="nav-item flex items-center px-4 py-3 rounded-xl text-sm font-bold border-l-4 <?php echo is_active('reports', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                        <i class="fas fa-file-invoice-dollar w-6 text-center text-lg <?php echo is_active('reports', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                        <span class="ml-2">Expense Ledger</span>
                    </a>
                </li>
            </ul>

            <div class="px-6 mb-2">
                <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest">Configuration</span>
            </div>

            <ul class="space-y-1 px-3 mb-6">
                <li>
                    <a href="<?php echo admin_url('banners'); ?>" class="nav-item flex items-center px-4 py-3 rounded-xl text-sm font-bold border-l-4 <?php echo is_active('banners', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                        <i class="far fa-images w-6 text-center text-lg <?php echo is_active('banners', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                        <span class="ml-2">Banners</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo admin_url('payments'); ?>" class="nav-item flex items-center px-4 py-3 rounded-xl text-sm font-bold border-l-4 <?php echo is_active('payments', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                        <i class="fas fa-university w-6 text-center text-lg <?php echo is_active('payments', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                        <span class="ml-2">Payment Gateways</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo admin_url('reviews'); ?>" class="nav-item flex items-center px-4 py-3 rounded-xl text-sm font-bold border-l-4 <?php echo is_active('reviews', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                        <i class="fas fa-star w-6 text-center text-lg <?php echo is_active('reviews', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                        <span class="ml-2">Review Logs</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo admin_url('settings'); ?>" class="nav-item flex items-center px-4 py-3 rounded-xl text-sm font-bold border-l-4 <?php echo is_active('settings', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                        <i class="fas fa-cogs w-6 text-center text-lg <?php echo is_active('settings', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                        <span class="ml-2">System Config</span>
                    </a>
                </li>
                <?php if ($admin_role === 'super_admin'): ?>
                <li>
                    <a href="<?php echo admin_url('admins'); ?>" class="nav-item flex items-center px-4 py-3 rounded-xl text-sm font-bold border-l-4 <?php echo is_active('admins', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                        <i class="fas fa-user-shield w-6 text-center text-lg <?php echo is_active('admins', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                        <span class="ml-2">Staff Management</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        
        <!-- Bottom Identity Block -->
        <div class="p-4 border-t border-slate-700/50 bg-slate-950 shrink-0">
            <div class="flex items-center gap-3 bg-slate-900 rounded-xl p-3 border border-slate-700 shadow-inner">
                <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-600 to-[#00f0ff] flex items-center justify-center text-slate-900 font-bold text-lg shadow-[0_0_10px_rgba(0,240,255,0.3)]">
                    <i class="fas fa-user-astronaut"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-white text-xs font-bold truncate">ID: <?php echo htmlspecialchars($_SESSION['admin_id']); ?></p>
                    <p class="text-[#00f0ff] text-[9px] uppercase font-bold tracking-widest truncate"><?php echo str_replace('_', ' ', $admin_role); ?></p>
                </div>
                <a href="logout.php" class="w-8 h-8 rounded-md bg-slate-800 hover:bg-red-500/20 hover:text-red-400 border border-slate-700 hover:border-red-500/30 flex items-center justify-center text-slate-400 transition" title="Sever Connection">
                    <i class="fas fa-power-off text-xs"></i>
                </a>
            </div>
        </div>

    </aside>

    <!-- ========================================== -->
    <!-- MAIN CONTENT AREA                          -->
    <!-- ========================================== -->
    <div class="flex-1 flex flex-col min-w-0 z-10 relative h-screen">
        
        <!-- Top Header Bar -->
        <header class="h-20 bg-slate-900/80 backdrop-blur-md border-b border-slate-700/50 flex items-center justify-between px-6 shrink-0 shadow-sm z-30">
            
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="lg:hidden w-10 h-10 rounded-xl bg-slate-800 border border-slate-600 text-slate-400 hover:text-[#00f0ff] flex items-center justify-center transition shadow-inner">
                    <i class="fas fa-bars"></i>
                </button>
                
                <h2 class="text-white font-bold text-lg hidden sm:block tracking-wide">
                    <?php echo ucfirst(str_replace('_', ' ', $current_page)); ?> Module
                </h2>
            </div>

            <div class="flex items-center gap-4">
                <div class="hidden md:flex items-center gap-2 bg-green-500/10 border border-green-500/20 px-3 py-1.5 rounded-lg shadow-inner">
                    <span class="w-2 h-2 rounded-full bg-green-400 animate-pulse shadow-[0_0_8px_#4ade80]"></span>
                    <span class="text-[10px] text-green-400 font-bold uppercase tracking-widest">Uplink Secure</span>
                </div>
                
                <!-- Quick Link to Frontend -->
                <a href="<?php echo defined('MAIN_SITE_URL') ? MAIN_SITE_URL : '../index.php'; ?>" target="_blank" class="flex items-center gap-2 bg-slate-800 hover:bg-slate-700 border border-slate-600 px-4 py-2 rounded-xl text-slate-300 hover:text-white transition text-xs font-bold uppercase tracking-wider shadow-sm group">
                    <i class="fas fa-external-link-alt text-[#00f0ff] group-hover:animate-pulse"></i> <span class="hidden sm:inline">View Public Matrix</span>
                </a>
            </div>
        </header>

        <!-- Dynamic Content Injection (Scrollable) -->
        <main class="flex-1 overflow-y-auto custom-scrollbar p-4 md:p-8 relative">
            <div class="max-w-7xl mx-auto w-full relative z-10 animate-fade-in-up pb-12">
            <!-- Included files will render their UI here -->

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobileOverlay');
        
        if (sidebar.classList.contains('-translate-x-full')) {
            // Open
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
            // Small delay for fade in
            setTimeout(() => { overlay.classList.add('opacity-100'); }, 10);
        } else {
            // Close
            sidebar.classList.add('-translate-x-full');
            overlay.classList.remove('opacity-100');
            setTimeout(() => { overlay.classList.add('hidden'); }, 300);
        }
    }
</script>