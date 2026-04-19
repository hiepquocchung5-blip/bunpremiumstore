<?php
// admin/includes/header.php
// PRODUCTION v6.0 - Mobile-Optimized (425px), Z-Index Supremacy & Live Clock

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

// Database connection required for badges
$pending_count = 0;
if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
        $pending_count = $stmt->fetchColumn();
    } catch (PDOException $e) {}
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
            background-color: #0f172a; 
            color: #f8fafc;
            overflow-x: hidden;
            -webkit-tap-highlight-color: transparent;
        }
        
        /* Webkit Scrollbar */
        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #00f0ff; }
        
        /* Sidebar custom scrollbar */
        .sidebar-scroll::-webkit-scrollbar { width: 3px; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(0, 240, 255, 0.2); }

        /* Animations */
        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 0 10px rgba(0, 240, 255, 0.3); }
            50% { box-shadow: 0 0 20px rgba(0, 240, 255, 0.6); }
        }
        .animate-pulse-glow { animation: pulse-glow 2s infinite; }
        
        .fade-in { animation: fadeIn 0.3s ease-in-out; }
        .animate-fade-in-down { animation: fadeInDown 0.4s ease-out forwards; }
        
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes fadeInDown { 
            from { opacity: 0; transform: translateY(-10px); } 
            to { opacity: 1; transform: translateY(0); } 
        }

        /* Nav Item Transitions */
        .nav-item { transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
        .nav-item:hover { background: rgba(255, 255, 255, 0.05); color: #fff; }
        .nav-item.active { 
            background: linear-gradient(90deg, rgba(0, 240, 255, 0.15) 0%, transparent 100%);
            color: #00f0ff; 
            border-left: 3px solid #00f0ff; 
            text-shadow: 0 0 10px rgba(0, 240, 255, 0.5);
        }
        
        /* Responsive Sidebar Drawer */
        #adminSidebar { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        #sidebarOverlay { transition: opacity 0.3s ease; }
    </style>
</head>
<body class="flex bg-[#0f172a] min-h-screen relative selection:bg-[#00f0ff]/30 selection:text-[#00f0ff]">

    <!-- Global Background FX -->
    <div class="fixed inset-0 w-full h-full -z-20 pointer-events-none">
        <div class="absolute top-[-10%] right-[-5%] w-[300px] md:w-[500px] h-[300px] md:h-[500px] bg-blue-600/10 rounded-full blur-[100px]"></div>
        <div class="absolute bottom-[-10%] left-[-5%] w-[300px] md:w-[500px] h-[300px] md:h-[500px] bg-[#00f0ff]/5 rounded-full blur-[100px]"></div>
    </div>

    <!-- ========================================== -->
    <!-- MOBILE HEADER (Visible < 1024px)           -->
    <!-- Z-Index 80 to float above normal content   -->
    <!-- ========================================== -->
    <div class="lg:hidden fixed top-0 left-0 right-0 h-16 bg-slate-900/95 backdrop-blur-xl border-b border-slate-800 z-[80] flex items-center justify-between px-4 sm:px-6 shadow-[0_4px_20px_rgba(0,0,0,0.5)]">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-slate-800 flex items-center justify-center border border-[#00f0ff]/30 shadow-[0_0_15px_rgba(0,240,255,0.2)]">
                <i class="fas fa-bolt text-[#00f0ff] text-sm"></i>
            </div>
            <div class="flex flex-col">
                <span class="font-black text-white text-base tracking-tight leading-none">DMMM <span class="text-[#00f0ff]">Admin</span></span>
                <span class="text-[8px] text-slate-400 uppercase tracking-widest font-bold mt-0.5 flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span> Online
                </span>
            </div>
        </div>
        
        <div class="flex items-center gap-3">
            <?php if($pending_count > 0): ?>
                <a href="<?php echo admin_url('orders', ['status' => 'pending']); ?>" class="w-9 h-9 rounded-xl bg-yellow-500/10 text-yellow-500 flex items-center justify-center border border-yellow-500/30 relative">
                    <i class="fas fa-bell animate-pulse"></i>
                    <span class="absolute -top-1 -right-1 w-4 h-4 bg-yellow-500 text-slate-900 text-[9px] font-black rounded-full flex items-center justify-center border border-slate-900"><?php echo $pending_count; ?></span>
                </a>
            <?php endif; ?>
            <button id="mobileMenuBtn" class="w-10 h-10 rounded-xl bg-slate-800 text-slate-300 flex items-center justify-center border border-slate-700 hover:text-white hover:border-[#00f0ff]/50 transition focus:outline-none shadow-inner">
                <i class="fas fa-bars text-lg"></i>
            </button>
        </div>
    </div>

    <!-- Mobile Overlay (Z-Index 90) -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-[90] hidden lg:hidden opacity-0 cursor-pointer"></div>

    <!-- ========================================== -->
    <!-- SIDEBAR NAVIGATION                         -->
    <!-- Z-Index 100 to dominate all UI elements    -->
    <!-- Width 64 (256px) fits 320-425px viewports  -->
    <!-- ========================================== -->
    <aside id="adminSidebar" class="fixed inset-y-0 left-0 w-64 bg-slate-900/95 backdrop-blur-2xl border-r border-slate-800 z-[100] transform -translate-x-full lg:translate-x-0 lg:static lg:flex shrink-0 flex-col shadow-[20px_0_50px_rgba(0,0,0,0.5)]">
        
        <!-- Brand Header -->
        <div class="h-20 flex items-center justify-between px-5 border-b border-slate-800 shrink-0 relative overflow-hidden group">
            <div class="absolute inset-0 bg-gradient-to-r from-blue-600/10 to-[#00f0ff]/5 opacity-0 group-hover:opacity-100 transition duration-500 pointer-events-none"></div>
            
            <div class="flex items-center gap-3 relative z-10">
                <div class="w-10 h-10 rounded-xl bg-slate-950 flex items-center justify-center border border-[#00f0ff]/50 shadow-[0_0_15px_rgba(0,240,255,0.3)]">
                    <i class="fas fa-satellite-dish text-[#00f0ff] text-lg group-hover:animate-pulse"></i>
                </div>
                <div class="flex flex-col">
                    <span class="font-black text-white text-xl tracking-tight leading-none">DMMM</span>
                    <span class="text-[9px] text-[#00f0ff] uppercase tracking-[0.2em] font-bold mt-0.5">Command Node</span>
                </div>
            </div>

            <!-- Mobile Close Btn inside sidebar -->
            <button id="closeSidebarBtn" class="lg:hidden text-slate-500 hover:text-white bg-slate-800 w-8 h-8 rounded-lg flex items-center justify-center transition border border-slate-700 focus:outline-none">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Identity Banner with Live Clock -->
        <div class="p-5 border-b border-slate-800/80 shrink-0 bg-slate-900/50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-slate-800 border border-[#00f0ff]/30 flex items-center justify-center text-white font-bold shadow-inner relative">
                    <?php echo strtoupper(substr($_SESSION['admin_role'] ?? 'A', 0, 1)); ?>
                    <span class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-green-500 border-2 border-slate-900 rounded-full"></span>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-white truncate">Op #<?php echo $_SESSION['admin_id'] ?? '1'; ?></p>
                    <p class="text-[9px] font-mono text-slate-400 uppercase tracking-widest truncate mt-0.5" id="live-clock">
                        --:--:--
                    </p>
                </div>
            </div>
        </div>

        <!-- Navigation Links -->
        <nav class="flex-1 overflow-y-auto sidebar-scroll py-4 space-y-1">
            
            <!-- OVERVIEW -->
            <div class="px-5 mb-2 mt-2">
                <span class="text-[9px] uppercase font-black text-slate-500 tracking-[0.2em]">Overview</span>
            </div>
            
            <a href="<?php echo admin_url('dashboard'); ?>" class="nav-item flex items-center px-5 py-2.5 mx-2 rounded-xl text-sm font-medium border-l-[3px] <?php echo is_active('dashboard', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                <i class="fas fa-chart-pie w-6 text-center text-lg <?php echo is_active('dashboard', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                <span class="ml-2 tracking-wide">Dashboard</span>
            </a>

            <!-- MANAGEMENT -->
            <div class="px-5 mb-2 mt-6">
                <span class="text-[9px] uppercase font-black text-slate-500 tracking-[0.2em]">Management</span>
            </div>

            <!-- Orders with Dynamic Badge -->
            <a href="<?php echo admin_url('orders'); ?>" class="nav-item flex items-center justify-between px-5 py-2.5 mx-2 rounded-xl text-sm font-medium border-l-[3px] <?php echo is_active('orders', $current_page) || is_active('order_detail', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                <div class="flex items-center">
                    <i class="fas fa-shopping-cart w-6 text-center text-lg <?php echo is_active('orders', $current_page) || is_active('order_detail', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                    <span class="ml-2 tracking-wide">Orders</span>
                </div>
                <?php if($pending_count > 0): ?>
                    <span class="bg-yellow-500 text-slate-900 text-[10px] font-black px-2 py-0.5 rounded border border-yellow-400 shadow-[0_0_10px_rgba(234,179,8,0.5)] animate-pulse"><?php echo $pending_count; ?></span>
                <?php endif; ?>
            </a>

            <a href="<?php echo admin_url('products'); ?>" class="nav-item flex items-center px-5 py-2.5 mx-2 rounded-xl text-sm font-medium border-l-[3px] <?php echo is_active('products', $current_page) || is_active('product_edit', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                <i class="fas fa-boxes w-6 text-center text-lg <?php echo is_active('products', $current_page) || is_active('product_edit', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                <span class="ml-2 tracking-wide">Products</span>
            </a>

            <a href="<?php echo admin_url('categories'); ?>" class="nav-item flex items-center px-5 py-2.5 mx-2 rounded-xl text-sm font-medium border-l-[3px] <?php echo is_active('categories', $current_page) || is_active('category_edit', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                <i class="fas fa-network-wired w-6 text-center text-lg <?php echo is_active('categories', $current_page) || is_active('category_edit', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                <span class="ml-2 tracking-wide">Categories</span>
            </a>

            <a href="<?php echo admin_url('keys'); ?>" class="nav-item flex items-center px-5 py-2.5 mx-2 rounded-xl text-sm font-medium border-l-[3px] <?php echo is_active('keys', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                <i class="fas fa-key w-6 text-center text-lg <?php echo is_active('keys', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                <span class="ml-2 tracking-wide">Stock / Keys</span>
            </a>

            <a href="<?php echo admin_url('users'); ?>" class="nav-item flex items-center px-5 py-2.5 mx-2 rounded-xl text-sm font-medium border-l-[3px] <?php echo is_active('users', $current_page) || is_active('user_detail', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                <i class="fas fa-users w-6 text-center text-lg <?php echo is_active('users', $current_page) || is_active('user_detail', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                <span class="ml-2 tracking-wide">Customers</span>
            </a>

            <a href="<?php echo admin_url('reviews'); ?>" class="nav-item flex items-center px-5 py-2.5 mx-2 rounded-xl text-sm font-medium border-l-[3px] <?php echo is_active('reviews', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                <i class="fas fa-star w-6 text-center text-lg <?php echo is_active('reviews', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                <span class="ml-2 tracking-wide">Reviews</span>
            </a>

            <!-- FINANCIALS & MARKETING -->
            <div class="px-5 mb-2 mt-6">
                <span class="text-[9px] uppercase font-black text-slate-500 tracking-[0.2em]">Finance & Growth</span>
            </div>

            <a href="<?php echo admin_url('pandl'); ?>" class="nav-item flex items-center px-5 py-2.5 mx-2 rounded-xl text-sm font-medium border-l-[3px] <?php echo is_active('pandl', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                <i class="fas fa-chart-line w-6 text-center text-lg <?php echo is_active('pandl', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                <span class="ml-2 tracking-wide">P&L Matrix</span>
            </a>

            <a href="<?php echo admin_url('reports'); ?>" class="nav-item flex items-center px-5 py-2.5 mx-2 rounded-xl text-sm font-medium border-l-[3px] <?php echo is_active('reports', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                <i class="fas fa-file-invoice-dollar w-6 text-center text-lg <?php echo is_active('reports', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                <span class="ml-2 tracking-wide">Expenses</span>
            </a>

            <a href="<?php echo admin_url('passes'); ?>" class="nav-item flex items-center px-5 py-2.5 mx-2 rounded-xl text-sm font-medium border-l-[3px] <?php echo is_active('passes', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                <i class="fas fa-crown w-6 text-center text-lg <?php echo is_active('passes', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                <span class="ml-2 tracking-wide">Agent Tiers</span>
            </a>

            <a href="<?php echo admin_url('coupons'); ?>" class="nav-item flex items-center px-5 py-2.5 mx-2 rounded-xl text-sm font-medium border-l-[3px] <?php echo is_active('coupons', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                <i class="fas fa-ticket-alt w-6 text-center text-lg <?php echo is_active('coupons', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                <span class="ml-2 tracking-wide">Promo Codes</span>
            </a>

            <a href="<?php echo admin_url('banners'); ?>" class="nav-item flex items-center px-5 py-2.5 mx-2 rounded-xl text-sm font-medium border-l-[3px] <?php echo is_active('banners', $current_page) || is_active('banner_edit', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                <i class="fas fa-images w-6 text-center text-lg <?php echo is_active('banners', $current_page) || is_active('banner_edit', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                <span class="ml-2 tracking-wide">Banners</span>
            </a>
            <a href="<?php echo admin_url('notifications'); ?>" class="nav-item flex items-center px-5 py-2.5 mx-2 rounded-xl text-sm font-medium border-l-[3px] <?php echo is_active('notifications', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                <i class="fas fa-bell w-6 text-center text-lg <?php echo is_active('notifications', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                <span class="ml-2 tracking-wide">Notifications</span>
            </a>

            <!-- SYSTEM -->
            <div class="px-5 mb-2 mt-6">
                <span class="text-[9px] uppercase font-black text-slate-500 tracking-[0.2em]">System</span>
            </div>

            <a href="<?php echo admin_url('payments'); ?>" class="nav-item flex items-center px-5 py-2.5 mx-2 rounded-xl text-sm font-medium border-l-[3px] <?php echo is_active('payments', $current_page) || is_active('payment_edit', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                <i class="fas fa-wallet w-6 text-center text-lg <?php echo is_active('payments', $current_page) || is_active('payment_edit', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                <span class="ml-2 tracking-wide">Payment Nodes</span>
            </a>
            
            <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
            <a href="<?php echo admin_url('admins'); ?>" class="nav-item flex items-center px-5 py-2.5 mx-2 rounded-xl text-sm font-medium border-l-[3px] <?php echo is_active('admins', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                <i class="fas fa-user-shield w-6 text-center text-lg <?php echo is_active('admins', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                <span class="ml-2 tracking-wide">Staff Roster</span>
            </a>
            <?php endif; ?>

            <a href="<?php echo admin_url('settings'); ?>" class="nav-item flex items-center px-5 py-2.5 mx-2 rounded-xl text-sm font-medium border-l-[3px] <?php echo is_active('settings', $current_page) ? 'active' : 'border-transparent text-slate-400'; ?>">
                <i class="fas fa-cogs w-6 text-center text-lg <?php echo is_active('settings', $current_page) ? 'text-[#00f0ff]' : 'opacity-70'; ?>"></i>
                <span class="ml-2 tracking-wide">Settings</span>
            </a>

            <div class="h-8"></div> <!-- Bottom Spacer -->
        </nav>

        <!-- Logout Action (Sticky at bottom) -->
        <div class="p-4 border-t border-slate-800/80 bg-slate-900 shrink-0">
            <a href="logout.php" class="flex items-center justify-center gap-2 w-full bg-red-500/10 hover:bg-red-500/20 text-red-400 hover:text-red-300 font-bold py-3 rounded-xl border border-red-500/20 transition-colors shadow-inner text-xs uppercase tracking-widest group">
                <i class="fas fa-power-off group-hover:scale-110 transition-transform"></i> Terminate
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT WRAPPER -->
    <!-- Added pt-16 to account for the fixed mobile header, ensuring content doesn't hide underneath -->
    <main class="flex-1 flex flex-col min-h-screen w-full lg:w-[calc(100%-16rem)] pt-16 lg:pt-0 overflow-x-hidden relative z-10 transition-all duration-300">
        
        <!-- Top Gradient Glow (Desktop) -->
        <div class="hidden lg:block absolute top-0 left-0 w-full h-32 bg-gradient-to-b from-slate-800/30 to-transparent pointer-events-none"></div>

        <!-- Content Area: The actual page content will be injected here by admin/index.php -->
        <div class="flex-1 p-4 sm:p-6 lg:p-8 animate-fade-in-down max-w-[1600px] w-full mx-auto pb-20">
            <!-- Child pages drop into here -->

            <!-- Mobile UI & Live Clock Script -->
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    // --- Sidebar Toggle Logic ---
                    const menuBtn = document.getElementById('mobileMenuBtn');
                    const closeBtn = document.getElementById('closeSidebarBtn');
                    const sidebar = document.getElementById('adminSidebar');
                    const overlay = document.getElementById('sidebarOverlay');

                    const toggleSidebar = () => {
                        const isOpen = !sidebar.classList.contains('-translate-x-full');
                        
                        if (isOpen) {
                            // Close
                            sidebar.classList.add('-translate-x-full');
                            overlay.classList.add('opacity-0');
                            setTimeout(() => {
                                overlay.classList.add('hidden');
                            }, 300); // match transition duration
                            document.body.style.overflow = '';
                        } else {
                            // Open
                            overlay.classList.remove('hidden');
                            // Small delay to allow display:block to apply before animating opacity
                            setTimeout(() => {
                                overlay.classList.remove('opacity-0');
                                sidebar.classList.remove('-translate-x-full');
                            }, 10);
                            document.body.style.overflow = 'hidden'; // Prevent background scrolling
                        }
                    };

                    if (menuBtn) menuBtn.addEventListener('click', toggleSidebar);
                    if (closeBtn) closeBtn.addEventListener('click', toggleSidebar);
                    if (overlay) overlay.addEventListener('click', toggleSidebar);

                    // --- Auto-Correct on Resize ---
                    window.addEventListener('resize', () => {
                        if (window.innerWidth >= 1024) {
                            // Desktop: ensure sidebar is visible and overlay is gone
                            sidebar.classList.remove('-translate-x-full');
                            overlay.classList.add('hidden', 'opacity-0');
                            document.body.style.overflow = '';
                        } else {
                            // Mobile: if overlay is hidden, ensure sidebar is hidden
                            if (overlay.classList.contains('hidden')) {
                                sidebar.classList.add('-translate-x-full');
                            }
                        }
                    });

                    // --- Live Matrix Clock ---
                    const clockEl = document.getElementById('live-clock');
                    if(clockEl) {
                        setInterval(() => {
                            const now = new Date();
                            const timeString = now.toLocaleTimeString('en-US', { hour12: false });
                            clockEl.innerHTML = `<i class="far fa-clock mr-1 text-slate-500"></i> ${timeString}`;
                        }, 1000);
                    }
                });
            </script>