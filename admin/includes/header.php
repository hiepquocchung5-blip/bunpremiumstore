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
    <title>Command Center - DigitalMarketplaceMM</title>
    
    <!-- Dependencies -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
    
    <!-- Custom CSS for Scrollbars & Animations -->
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #020617; 
            color: #f8fafc;
            overflow: hidden; /* Maintained by internal flex containers */
            -webkit-tap-highlight-color: transparent;
        }
        
        /* Webkit Scrollbar */
        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #00f0ff; }
        
        /* Sidebar custom scrollbar - ultra thin */
        .sidebar-scroll::-webkit-scrollbar { width: 2px; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(0, 240, 255, 0.2); }
        .sidebar-scroll:hover::-webkit-scrollbar-thumb { background: rgba(0, 240, 255, 0.5); }

        /* Animations */
        @keyframes pulse-slow {
            0%, 100% { opacity: 0.15; }
            50% { opacity: 0.3; }
        }
        .animate-pulse-slow { animation: pulse-slow 4s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
        
        /* Nav Item Transitions */
        .nav-item { transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
        .nav-item:hover { background: rgba(0, 240, 255, 0.05); color: #fff; border-right-color: rgba(0, 240, 255, 0.5); }
        .nav-item.active { 
            background: linear-gradient(90deg, rgba(0, 240, 255, 0.1) 0%, transparent 100%);
            color: #00f0ff; 
            border-left: 3px solid #00f0ff; 
            text-shadow: 0 0 10px rgba(0, 240, 255, 0.3);
        }
        
        /* Loader Dissolve */
        .loader-exit { opacity: 0; pointer-events: none; transition: opacity 0.5s ease-out; }
    </style>

    <!-- Global App Configuration (Failsafe for Push APIs) -->
    <script>
        window.AppConfig = {
            vapidPublicKey: "<?php echo $_ENV['VAPID_PUBLIC_KEY'] ?? ''; ?>",
            baseUrl: "<?php echo defined('MAIN_SITE_URL') ? MAIN_SITE_URL : '/'; ?>"
        };
    </script>
</head>
<body class="flex bg-[#020617] h-[100dvh] w-full selection:bg-[#00f0ff]/30 selection:text-[#00f0ff] relative">

    <!-- MATRIX LOADER (Z-Index Maximum) -->
    <div id="matrixLoader" class="fixed inset-0 z-[9999] bg-[#020617] flex flex-col items-center justify-center backdrop-blur-3xl">
        <div class="relative w-20 h-20 flex items-center justify-center mb-4">
            <div class="absolute inset-0 rounded-full border-t-2 border-r-2 border-[#00f0ff] animate-spin shadow-[0_0_20px_#00f0ff]"></div>
            <div class="absolute inset-2 rounded-full border-b-2 border-l-2 border-purple-500 animate-[spin_1.5s_linear_infinite_reverse]"></div>
            <i class="fas fa-satellite-dish text-[#00f0ff] text-xl animate-pulse"></i>
        </div>
        <div class="text-[#00f0ff] font-mono text-[10px] uppercase tracking-[0.3em] font-black animate-pulse">Initializing Matrix...</div>
    </div>

    <!-- Global Background FX -->
    <div class="fixed inset-0 w-full h-full z-0 pointer-events-none">
        <div class="absolute top-[-10%] right-[-5%] w-[300px] md:w-[500px] h-[300px] md:h-[500px] bg-blue-600/10 rounded-full blur-[100px] animate-pulse-slow"></div>
        <div class="absolute bottom-[-10%] left-[-5%] w-[300px] md:w-[500px] h-[300px] md:h-[500px] bg-purple-600/10 rounded-full blur-[100px] animate-pulse-slow" style="animation-delay: 2s;"></div>
    </div>

    <!-- Mobile Overlay (Z-Index 90) -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-[90] hidden lg:hidden opacity-0 transition-opacity duration-300 cursor-pointer" onclick="toggleSidebar()"></div>

    <!-- ========================================== -->
    <!-- SIDEBAR NAVIGATION                         -->
    <!-- Z-Index 100 to dominate all UI elements    -->
    <!-- Width 64 (256px) / Compact Layout          -->
    <!-- ========================================== -->
    <aside id="adminSidebar" class="fixed inset-y-0 left-0 w-64 bg-slate-900/95 backdrop-blur-2xl border-r border-slate-800 z-[100] transform -translate-x-full lg:translate-x-0 lg:static flex shrink-0 flex-col shadow-[20px_0_50px_rgba(0,0,0,0.5)] transition-transform duration-300">
        
        <!-- Brand Header -->
        <div class="h-16 lg:h-20 flex items-center justify-between px-5 border-b border-slate-800/80 shrink-0 relative overflow-hidden group bg-slate-950">
            <div class="absolute inset-0 bg-gradient-to-r from-blue-600/5 to-[#00f0ff]/5 opacity-0 group-hover:opacity-100 transition duration-500 pointer-events-none"></div>
            
            <a href="index.php" class="flex items-center gap-3 relative z-10 w-full">
                <div class="w-8 h-8 lg:w-10 lg:h-10 rounded-xl bg-slate-900 flex items-center justify-center border border-[#00f0ff]/30 shadow-[0_0_10px_rgba(0,240,255,0.2)] group-hover:border-[#00f0ff] transition-colors">
                    <i class="fas fa-bolt text-[#00f0ff] text-base lg:text-lg group-hover:scale-110 transition-transform"></i>
                </div>
                <div class="flex flex-col">
                    <span class="font-black text-white text-base lg:text-lg tracking-tight leading-none group-hover:text-[#00f0ff] transition-colors">Matrix</span>
                    <span class="text-[8px] lg:text-[9px] text-slate-500 uppercase tracking-[0.2em] font-bold mt-0.5">Command Node</span>
                </div>
            </a>
        </div>

        <!-- Navigation Links (Compact Padding) -->
        <nav class="flex-1 overflow-y-auto sidebar-scroll py-3 space-y-0.5">
            
            <div class="px-5 mb-1.5 mt-2">
                <span class="text-[8px] uppercase font-black text-slate-600 tracking-[0.2em]">Core Systems</span>
            </div>
            
            <a href="<?php echo admin_url('dashboard'); ?>" class="nav-item flex items-center px-4 py-2 mx-2 rounded-lg text-[13px] font-semibold border-l-[3px] <?php echo is_active('dashboard', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                <i class="fas fa-chart-pie w-6 text-center text-base <?php echo is_active('dashboard', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                <span class="ml-2 tracking-wide">Dashboard</span>
            </a>

            <a href="<?php echo admin_url('orders'); ?>" class="nav-item flex items-center justify-between px-4 py-2 mx-2 rounded-lg text-[13px] font-semibold border-l-[3px] <?php echo is_active('orders', $current_page) || is_active('order_detail', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                <div class="flex items-center">
                    <i class="fas fa-shopping-cart w-6 text-center text-base <?php echo is_active('orders', $current_page) || is_active('order_detail', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                    <span class="ml-2 tracking-wide">Orders</span>
                </div>
                <?php if($pending_count > 0): ?>
                    <span class="bg-yellow-500/20 text-yellow-400 text-[9px] font-black px-1.5 py-0.5 rounded shadow-[0_0_8px_rgba(234,179,8,0.3)] animate-pulse"><?php echo $pending_count; ?></span>
                <?php endif; ?>
            </a>

            <a href="<?php echo admin_url('users'); ?>" class="nav-item flex items-center px-4 py-2 mx-2 rounded-lg text-[13px] font-semibold border-l-[3px] <?php echo is_active('users', $current_page) || is_active('user_detail', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                <i class="fas fa-users w-6 text-center text-base <?php echo is_active('users', $current_page) || is_active('user_detail', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                <span class="ml-2 tracking-wide">Operatives</span>
            </a>

            <div class="px-5 mb-1.5 mt-4 border-t border-slate-800/50 pt-3">
                <span class="text-[8px] uppercase font-black text-slate-600 tracking-[0.2em]">Inventory Matrix</span>
            </div>

            <a href="<?php echo admin_url('products'); ?>" class="nav-item flex items-center px-4 py-2 mx-2 rounded-lg text-[13px] font-semibold border-l-[3px] <?php echo is_active('products', $current_page) || is_active('product_edit', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                <i class="fas fa-boxes w-6 text-center text-base <?php echo is_active('products', $current_page) || is_active('product_edit', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                <span class="ml-2 tracking-wide">Digital Assets</span>
            </a>

            <a href="<?php echo admin_url('categories'); ?>" class="nav-item flex items-center px-4 py-2 mx-2 rounded-lg text-[13px] font-semibold border-l-[3px] <?php echo is_active('categories', $current_page) || is_active('category_edit', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                <i class="fas fa-network-wired w-6 text-center text-base <?php echo is_active('categories', $current_page) || is_active('category_edit', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                <span class="ml-2 tracking-wide">Sectors</span>
            </a>

            <a href="<?php echo admin_url('keys'); ?>" class="nav-item flex items-center px-4 py-2 mx-2 rounded-lg text-[13px] font-semibold border-l-[3px] <?php echo is_active('keys', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                <i class="fas fa-key w-6 text-center text-base <?php echo is_active('keys', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                <span class="ml-2 tracking-wide">Stock / Keys</span>
            </a>

            <div class="px-5 mb-1.5 mt-4 border-t border-slate-800/50 pt-3">
                <span class="text-[8px] uppercase font-black text-slate-600 tracking-[0.2em]">Growth & Finance</span>
            </div>

            <a href="<?php echo admin_url('pandl'); ?>" class="nav-item flex items-center px-4 py-2 mx-2 rounded-lg text-[13px] font-semibold border-l-[3px] <?php echo is_active('pandl', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                <i class="fas fa-chart-line w-6 text-center text-base <?php echo is_active('pandl', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                <span class="ml-2 tracking-wide">P&L Matrix</span>
            </a>

            <a href="<?php echo admin_url('reports'); ?>" class="nav-item flex items-center px-4 py-2 mx-2 rounded-lg text-[13px] font-semibold border-l-[3px] <?php echo is_active('reports', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                <i class="fas fa-file-invoice-dollar w-6 text-center text-base <?php echo is_active('reports', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                <span class="ml-2 tracking-wide">Expenses</span>
            </a>

            <a href="<?php echo admin_url('passes'); ?>" class="nav-item flex items-center px-4 py-2 mx-2 rounded-lg text-[13px] font-semibold border-l-[3px] <?php echo is_active('passes', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                <i class="fas fa-crown w-6 text-center text-base <?php echo is_active('passes', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                <span class="ml-2 tracking-wide">Agent Tiers</span>
            </a>

            <a href="<?php echo admin_url('coupons'); ?>" class="nav-item flex items-center px-4 py-2 mx-2 rounded-lg text-[13px] font-semibold border-l-[3px] <?php echo is_active('coupons', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                <i class="fas fa-ticket-alt w-6 text-center text-base <?php echo is_active('coupons', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                <span class="ml-2 tracking-wide">Promo Codes</span>
            </a>

            <a href="<?php echo admin_url('notifications'); ?>" class="nav-item flex items-center justify-between px-4 py-2 mx-2 rounded-lg text-[13px] font-semibold border-l-[3px] <?php echo is_active('notifications', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                <div class="flex items-center">
                    <i class="fas fa-satellite-dish w-6 text-center text-base <?php echo is_active('notifications', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                    <span class="ml-2 tracking-wide">Push Matrix</span>
                </div>
                <?php if($push_nodes_count > 0): ?>
                    <span class="bg-blue-500/10 text-blue-400 text-[9px] font-mono px-1 rounded border border-blue-500/20"><?php echo $push_nodes_count; ?></span>
                <?php endif; ?>
            </a>

            <div class="px-5 mb-1.5 mt-4 border-t border-slate-800/50 pt-3">
                <span class="text-[8px] uppercase font-black text-slate-600 tracking-[0.2em]">System Config</span>
            </div>

            <a href="<?php echo admin_url('banners'); ?>" class="nav-item flex items-center px-4 py-2 mx-2 rounded-lg text-[13px] font-semibold border-l-[3px] <?php echo is_active('banners', $current_page) || is_active('banner_edit', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                <i class="fas fa-images w-6 text-center text-base <?php echo is_active('banners', $current_page) || is_active('banner_edit', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                <span class="ml-2 tracking-wide">Banners</span>
            </a>

            <a href="<?php echo admin_url('payments'); ?>" class="nav-item flex items-center px-4 py-2 mx-2 rounded-lg text-[13px] font-semibold border-l-[3px] <?php echo is_active('payments', $current_page) || is_active('payment_edit', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                <i class="fas fa-wallet w-6 text-center text-base <?php echo is_active('payments', $current_page) || is_active('payment_edit', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                <span class="ml-2 tracking-wide">Payment Nodes</span>
            </a>
            
            <a href="<?php echo admin_url('settings'); ?>" class="nav-item flex items-center px-4 py-2 mx-2 rounded-lg text-[13px] font-semibold border-l-[3px] <?php echo is_active('settings', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                <i class="fas fa-cogs w-6 text-center text-base <?php echo is_active('settings', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                <span class="ml-2 tracking-wide">Settings</span>
            </a>

            <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
            <a href="<?php echo admin_url('admins'); ?>" class="nav-item flex items-center px-4 py-2 mx-2 rounded-lg text-[13px] font-semibold border-l-[3px] <?php echo is_active('admins', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                <i class="fas fa-user-shield w-6 text-center text-base <?php echo is_active('admins', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                <span class="ml-2 tracking-wide">Staff Roster</span>
            </a>
            <?php endif; ?>

            <div class="h-4"></div> <!-- Small Bottom Spacer -->
        </nav>

        <!-- Identity & Logout Block -->
        <div class="p-3 border-t border-slate-800/80 bg-slate-950 shrink-0">
            <div class="flex items-center gap-3 bg-slate-900 rounded-lg p-2.5 border border-slate-800 shadow-inner">
                <div class="w-8 h-8 rounded bg-gradient-to-br from-blue-600 to-[#00f0ff] flex items-center justify-center text-slate-900 font-bold text-sm shadow-[0_0_10px_rgba(0,240,255,0.3)] shrink-0">
                    <i class="fas fa-user-astronaut"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-white text-xs font-bold truncate tracking-wide">Op #<?php echo htmlspecialchars($_SESSION['admin_id']); ?></p>
                    <p class="text-[#00f0ff] text-[8px] uppercase font-bold tracking-[0.2em] truncate"><?php echo str_replace('_', ' ', $_SESSION['admin_role'] ?? 'Support'); ?></p>
                </div>
                <a href="logout.php" class="w-8 h-8 rounded bg-slate-800 hover:bg-red-500/20 hover:text-red-400 border border-slate-700 hover:border-red-500/30 flex items-center justify-center text-slate-400 transition shrink-0 group" title="Sever Connection">
                    <i class="fas fa-power-off text-xs group-hover:scale-110 transition-transform"></i>
                </a>
            </div>
        </div>
    </aside>

    <!-- ========================================== -->
    <!-- MAIN CONTENT AREA                          -->
    <!-- ========================================== -->
    <div class="flex-1 flex flex-col min-w-0 z-10 relative h-[100dvh]">
        
        <!-- Top Header Bar (Z-Index Supremacy - Responsive) -->
        <header class="h-16 lg:h-20 bg-slate-900/80 backdrop-blur-xl border-b border-slate-700/50 flex items-center justify-between px-4 sm:px-6 shrink-0 shadow-sm z-[80]">
            
            <div class="flex items-center gap-3 sm:gap-4">
                <button onclick="toggleSidebar()" class="lg:hidden w-10 h-10 rounded-xl bg-slate-800 border border-slate-600 text-slate-400 hover:text-[#00f0ff] flex items-center justify-center transition shadow-inner shrink-0 hover:shadow-[0_0_15px_rgba(0,240,255,0.2)]">
                    <i class="fas fa-bars"></i>
                </button>
                
                <h2 class="text-white font-bold text-base sm:text-lg hidden min-[425px]:block tracking-wide truncate max-w-[150px] sm:max-w-none">
                    <?php echo ucfirst(str_replace('_', ' ', $current_page)); ?> Module
                </h2>
            </div>

            <div class="flex items-center gap-3 sm:gap-4">
                
                <!-- Live Telemetry Clock -->
                <div class="hidden min-[425px]:flex items-center gap-2 bg-slate-800 border border-slate-700 px-3 py-1.5 rounded-lg shadow-inner text-slate-300 font-mono text-[10px] tracking-widest transition-colors hover:border-[#00f0ff]/30">
                    <i class="far fa-clock text-[#00f0ff]"></i>
                    <span id="live-clock">00:00:00</span>
                </div>

                <div class="hidden md:flex items-center gap-2 bg-green-500/10 border border-green-500/20 px-3 py-1.5 rounded-lg shadow-inner">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse shadow-[0_0_8px_#4ade80]"></span>
                    <span class="text-[9px] text-green-400 font-bold uppercase tracking-widest">Uplink Secure</span>
                </div>
                
                <!-- Quick Link to Frontend -->
                <a href="<?php echo defined('MAIN_SITE_URL') ? MAIN_SITE_URL : '../index.php'; ?>" target="_blank" class="flex items-center justify-center gap-2 bg-slate-800 hover:bg-slate-700 border border-slate-600 w-10 h-10 sm:w-auto sm:px-4 sm:py-2 rounded-xl text-slate-300 hover:text-white transition text-xs font-bold uppercase tracking-wider shadow-sm group shrink-0">
                    <i class="fas fa-external-link-alt text-[#00f0ff] group-hover:animate-pulse"></i> 
                    <span class="hidden sm:inline">View Public Matrix</span>
                </a>
            </div>
        </header>

        <!-- Dynamic Content Injection (Independent Scrolling Area) -->
        <main class="flex-1 overflow-y-auto custom-scrollbar p-4 md:p-6 lg:p-8 relative w-full">
            <div class="max-w-[1600px] mx-auto w-full relative z-10 animate-fade-in-up pb-12">
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