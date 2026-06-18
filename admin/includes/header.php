<?php
// admin/includes/header.php
// PRODUCTION v6.5 - Dynamic DVH, Matrix Loader & Compact Sidebar UI

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Check: Ensure Admin is Logged In
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Current Page Detection Helper
$current_page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

function is_active($page, $current_page) {
    return $page === $current_page;
}

// URL Helper (Fallback if not defined in functions)
if (!function_exists('admin_url')) {
    function admin_url($page, $params = []) {
        $url = "index.php?page=" . $page;
        if (!empty($params)) {
            foreach ($params as $k => $v) {
                $url .= "&" . urlencode($k) . "=" . urlencode($v);
            }
        }
        return $url;
    }
}

// Database Connection & Telemetry Routines
$pending_count = 0;
$push_nodes_count = 0;

if (isset($pdo)) {
    try {
        // 1. Fetch Pending Orders
        $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
        $pending_count = $stmt->fetchColumn();

        // 2. Fetch Active Push Nodes
        $stmt_push = $pdo->query("SELECT COUNT(*) FROM push_subscriptions");
        $push_nodes_count = $stmt_push->fetchColumn();

    } catch (PDOException $e) {
        // Fails gracefully if DB lacks permissions
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Dashboard | DigitalMM</title>
    
    <!-- Dependencies -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-main: #0a0c12;
            --bg-sidebar: #11141d;
            --primary: #4f46e5;
            --primary-light: #6366f1;
            --accent: #8b5cf6;
            --surface: #1a1e29;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }

        body { 
            font-family: 'Inter', sans-serif; 
            background-color: var(--bg-main); 
            color: var(--text-main);
            overflow: hidden;
            -webkit-tap-highlight-color: transparent;
        }

        h1, h2, h3, h4, h5, h6, .font-heading {
            font-family: 'Outfit', sans-serif;
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--primary); }

        .nav-item { 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
            position: relative;
        }
        .nav-item:hover { 
            background: rgba(79, 70, 229, 0.08); 
            color: #fff;
            transform: translateX(4px);
        }
        .nav-item.active { 
            background: linear-gradient(90deg, rgba(79, 70, 229, 0.15) 0%, transparent 100%);
            color: var(--primary-light); 
            font-weight: 600;
        }
        .nav-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 20%;
            height: 60%;
            width: 4px;
            background: var(--primary);
            border-radius: 0 4px 4px 0;
            box-shadow: 2px 0 10px rgba(79, 70, 229, 0.4);
        }
        
        #adminSidebar { 
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); 
            background: var(--bg-sidebar);
        }

        .glass-header { 
            background: rgba(10, 12, 18, 0.8); 
            backdrop-filter: blur(20px); 
            border-bottom: 1px solid rgba(255, 255, 255, 0.03); 
        }

        .custom-card {
            background: var(--surface);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 24px;
            transition: all 0.3s ease;
        }
        .custom-card:hover {
            border-color: rgba(79, 70, 229, 0.2);
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.5);
        }

        .animate-fade-in {
            animation: fadeIn 0.5s ease-out forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive adjustments */
        @media (max-width: 1024px) {
            .main-content-padding { padding: 1.5rem; }
        }
    </style>

    <script>
        window.AppConfig = {
            vapidPublicKey: "<?php echo $_ENV['VAPID_PUBLIC_KEY'] ?? ''; ?>",
            baseUrl: "<?php echo defined('MAIN_SITE_URL') ? MAIN_SITE_URL : '/'; ?>"
        };
    </script>
</head>
<body class="flex h-[100dvh] w-full relative">

    <!-- Mobile Sidebar Overlay -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-black/70 backdrop-blur-md z-[90] hidden lg:hidden opacity-0 transition-opacity duration-300" onclick="toggleSidebar()"></div>

    <!-- Sidebar Navigation -->
    <aside id="adminSidebar" class="fixed inset-y-0 left-0 w-72 border-r border-white/5 z-[100] transform -translate-x-full lg:translate-x-0 lg:static flex flex-col shadow-2xl">
        
        <!-- Logo Area -->
        <div class="h-20 flex items-center px-8 border-b border-white/5">
            <a href="index.php" class="flex items-center gap-3 group">
                <div class="w-10 h-10 rounded-2xl bg-indigo-600 flex items-center justify-center text-white shadow-lg shadow-indigo-500/30 group-hover:rotate-6 transition-transform">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <div class="flex flex-col">
                    <span class="font-bold text-white text-xl tracking-tight leading-none font-heading">Admin<span class="text-indigo-500">MM</span></span>
                    <span class="text-[10px] text-slate-500 uppercase tracking-[0.2em] font-bold mt-1">Management Hub</span>
                </div>
            </a>
        </div>

        <!-- Scrollable Menu -->
        <nav class="flex-1 overflow-y-auto py-8 space-y-1 px-4 custom-scrollbar">
            
            <div class="px-4 mb-4 mt-2">
                <span class="text-[11px] uppercase font-bold text-slate-600 tracking-[0.2em]">Core Console</span>
            </div>
            
            <a href="<?php echo admin_url('dashboard'); ?>" class="nav-item flex items-center px-4 py-3.5 rounded-2xl text-[14px] <?php echo is_active('dashboard', $current_page) ? 'active' : 'text-slate-400 hover:text-white'; ?>">
                <i class="fas fa-grid-2 w-6 text-center text-lg opacity-80"></i>
                <span class="ml-3">Overview</span>
            </a>

            <a href="<?php echo admin_url('orders'); ?>" class="nav-item flex items-center justify-between px-4 py-3.5 rounded-2xl text-[14px] <?php echo is_active('orders', $current_page) || is_active('order_detail', $current_page) ? 'active' : 'text-slate-400 hover:text-white'; ?>">
                <div class="flex items-center">
                    <i class="fas fa-shopping-bag w-6 text-center text-lg opacity-80"></i>
                    <span class="ml-3">Order Management</span>
                </div>
                <?php if($pending_count > 0): ?>
                    <span class="bg-indigo-600 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-lg shadow-indigo-500/30"><?php echo $pending_count; ?></span>
                <?php endif; ?>
            </a>

            <a href="<?php echo admin_url('users'); ?>" class="nav-item flex items-center px-4 py-3.5 rounded-2xl text-[14px] <?php echo is_active('users', $current_page) || is_active('user_detail', $current_page) ? 'active' : 'text-slate-400 hover:text-white'; ?>">
                <i class="fas fa-users w-6 text-center text-lg opacity-80"></i>
                <span class="ml-3">Customer Base</span>
            </a>

            <div class="px-4 mb-4 mt-10">
                <span class="text-[11px] uppercase font-bold text-slate-600 tracking-[0.2em]">Inventory Hub</span>
            </div>

            <a href="<?php echo admin_url('products'); ?>" class="nav-item flex items-center px-4 py-3.5 rounded-2xl text-[14px] <?php echo is_active('products', $current_page) || is_active('product_edit', $current_page) ? 'active' : 'text-slate-400 hover:text-white'; ?>">
                <i class="fas fa-box w-6 text-center text-lg opacity-80"></i>
                <span class="ml-3">Product Catalog</span>
            </a>

            <a href="<?php echo admin_url('categories'); ?>" class="nav-item flex items-center px-4 py-3.5 rounded-2xl text-[14px] <?php echo is_active('categories', $current_page) || is_active('category_edit', $current_page) ? 'active' : 'text-slate-400 hover:text-white'; ?>">
                <i class="fas fa-layer-group w-6 text-center text-lg opacity-80"></i>
                <span class="ml-3">Category Grid</span>
            </a>

            <a href="<?php echo admin_url('keys'); ?>" class="nav-item flex items-center px-4 py-3.5 rounded-2xl text-[14px] <?php echo is_active('keys', $current_page) ? 'active' : 'text-slate-400 hover:text-white'; ?>">
                <i class="fas fa-key w-6 text-center text-lg opacity-80"></i>
                <span class="ml-3">Digital Inventory</span>
            </a>

            <div class="px-4 mb-4 mt-10">
                <span class="text-[11px] uppercase font-bold text-slate-600 tracking-[0.2em]">Growth & Finance</span>
            </div>

            <a href="<?php echo admin_url('pandl'); ?>" class="nav-item flex items-center px-4 py-3.5 rounded-2xl text-[14px] <?php echo is_active('pandl', $current_page) ? 'active' : 'text-slate-400 hover:text-white'; ?>">
                <i class="fas fa-chart-line w-6 text-center text-lg opacity-80"></i>
                <span class="ml-3">Profit & Revenue</span>
            </a>

            <a href="<?php echo admin_url('reports'); ?>" class="nav-item flex items-center px-4 py-3.5 rounded-2xl text-[14px] <?php echo is_active('reports', $current_page) ? 'active' : 'text-slate-400 hover:text-white'; ?>">
                <i class="fas fa-file-invoice w-6 text-center text-lg opacity-80"></i>
                <span class="ml-3">Expense Tracker</span>
            </a>

            <a href="<?php echo admin_url('passes'); ?>" class="nav-item flex items-center px-4 py-3.5 rounded-2xl text-[14px] <?php echo is_active('passes', $current_page) ? 'active' : 'text-slate-400 hover:text-white'; ?>">
                <i class="fas fa-crown w-6 text-center text-lg opacity-80"></i>
                <span class="ml-3">Agent Tiers</span>
            </a>

            <a href="<?php echo admin_url('coupons'); ?>" class="nav-item flex items-center px-4 py-3.5 rounded-2xl text-[14px] <?php echo is_active('coupons', $current_page) ? 'active' : 'text-slate-400 hover:text-white'; ?>">
                <i class="fas fa-ticket w-6 text-center text-lg opacity-80"></i>
                <span class="ml-3">Promo Coupons</span>
            </a>

            <a href="<?php echo admin_url('notifications'); ?>" class="nav-item flex items-center justify-between px-4 py-3.5 rounded-2xl text-[14px] <?php echo is_active('notifications', $current_page) ? 'active' : 'text-slate-400 hover:text-white'; ?>">
                <div class="flex items-center">
                    <i class="fas fa-bell w-6 text-center text-lg opacity-80"></i>
                    <span class="ml-3">Push Campaigns</span>
                </div>
                <?php if($push_nodes_count > 0): ?>
                    <span class="text-[10px] font-bold text-indigo-400"><?php echo $push_nodes_count; ?> Nodes</span>
                <?php endif; ?>
            </a>

            <div class="px-4 mb-4 mt-10">
                <span class="text-[11px] uppercase font-bold text-slate-600 tracking-[0.2em]">Engine Room</span>
            </div>

            <a href="<?php echo admin_url('banners'); ?>" class="nav-item flex items-center px-4 py-3.5 rounded-2xl text-[14px] <?php echo is_active('banners', $current_page) || is_active('banner_edit', $current_page) ? 'active' : 'text-slate-400 hover:text-white'; ?>">
                <i class="fas fa-images w-6 text-center text-lg opacity-80"></i>
                <span class="ml-3">Visual Banners</span>
            </a>

            <a href="<?php echo admin_url('payments'); ?>" class="nav-item flex items-center px-4 py-3.5 rounded-2xl text-[14px] <?php echo is_active('payments', $current_page) || is_active('payment_edit', $current_page) ? 'active' : 'text-slate-400 hover:text-white'; ?>">
                <i class="fas fa-credit-card w-6 text-center text-lg opacity-80"></i>
                <span class="ml-3">Payment Gateways</span>
            </a>
            
            <a href="<?php echo admin_url('settings'); ?>" class="nav-item flex items-center px-4 py-3.5 rounded-2xl text-[14px] <?php echo is_active('settings', $current_page) ? 'active' : 'text-slate-400 hover:text-white'; ?>">
                <i class="fas fa-cog w-6 text-center text-lg opacity-80"></i>
                <span class="ml-3">System Settings</span>
            </a>

            <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
            <a href="<?php echo admin_url('admins'); ?>" class="nav-item flex items-center px-4 py-3.5 rounded-2xl text-[14px] <?php echo is_active('admins', $current_page) ? 'active' : 'text-slate-400 hover:text-white'; ?>">
                <i class="fas fa-user-shield w-6 text-center text-lg opacity-80"></i>
                <span class="ml-3">Staff Management</span>
            </a>
            <?php endif; ?>
        </nav>

        <!-- Profile / Logout -->
        <div class="p-6 border-t border-white/5 bg-black/10">
            <div class="flex items-center gap-4 bg-slate-900/40 p-4 rounded-[20px] border border-white/5 group hover:border-indigo-500/30 transition-all">
                <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-base shadow-lg">
                    <?php echo strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)); ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-white text-xs font-bold truncate tracking-tight"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></p>
                    <p class="text-slate-500 text-[10px] uppercase font-bold tracking-[0.15em] mt-0.5"><?php echo $_SESSION['admin_role'] ?? 'Staff'; ?></p>
                </div>
                <a href="logout.php" class="text-slate-500 hover:text-rose-500 transition-colors p-2" title="Logout">
                    <i class="fas fa-power-off text-sm"></i>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content Wrapper -->
    <div class="flex-1 flex flex-col min-w-0 h-[100dvh] relative">
        
        <!-- Navbar -->
        <header class="h-20 glass-header flex items-center justify-between px-8 shrink-0 z-50">
            <div class="flex items-center gap-6">
                <button onclick="toggleSidebar()" class="lg:hidden w-12 h-12 rounded-2xl bg-slate-800/50 border border-white/10 text-slate-300 flex items-center justify-center transition-all hover:bg-slate-700 active:scale-95">
                    <i class="fas fa-bars-staggered"></i>
                </button>
                <div class="flex flex-col">
                    <h2 class="text-white font-bold text-xl hidden sm:block tracking-tight font-heading">
                        <?php echo ucfirst(str_replace('_', ' ', $current_page)); ?>
                    </h2>
                    <div class="flex items-center gap-2 mt-0.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="text-[9px] text-slate-500 font-bold uppercase tracking-[0.2em]">Live Production Environment</span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <a href="../index.php" target="_blank" class="flex items-center gap-3 bg-indigo-600/10 hover:bg-indigo-600 border border-indigo-500/20 hover:border-indigo-500 px-6 py-2.5 rounded-[16px] text-indigo-400 hover:text-white transition-all duration-300 text-[11px] font-bold uppercase tracking-[0.1em] shadow-sm">
                    <i class="fas fa-external-link-alt text-xs"></i> 
                    <span class="hidden md:inline">Live Storefront</span>
                </a>
            </div>
        </header>

        <!-- Independent Scrollable Main -->
        <main class="flex-1 overflow-y-auto p-8 md:p-10 lg:p-12 custom-scrollbar">
            <div class="max-w-[1600px] mx-auto w-full animate-fade-in">
            <!-- Included files will render their UI here -->

<script>
    // --- Matrix Loader Dissolve Logic ---
    window.addEventListener('load', () => {
        const loader = document.getElementById('matrixLoader');
        if (loader) {
            loader.classList.add('loader-exit');
            setTimeout(() => { loader.remove(); }, 500); // Fully remove from DOM after fade
        }
    });

    // --- Sidebar Toggle Logic ---
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('sidebarOverlay');

    function toggleSidebar() {
        const isOpen = !sidebar.classList.contains('-translate-x-full');
        
        if (isOpen) {
            // Close
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('opacity-0');
            setTimeout(() => { overlay.classList.add('hidden'); }, 300);
        } else {
            // Open
            overlay.classList.remove('hidden');
            setTimeout(() => {
                overlay.classList.remove('opacity-0');
                sidebar.classList.remove('-translate-x-full');
            }, 10);
        }
    }

    // Auto-Correct Sidebar on Resize
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 1024) {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.add('hidden', 'opacity-0');
        } else {
            if (overlay.classList.contains('hidden')) {
                sidebar.classList.add('-translate-x-full');
            }
        }
    });

    // --- Live Matrix Clock ---
    const clockEl = document.getElementById('live-clock');
    if (clockEl) {
        setInterval(() => {
            const now = new Date();
            clockEl.innerText = now.toLocaleTimeString('en-US', { hour12: false });
        }, 1000);
    }
</script>