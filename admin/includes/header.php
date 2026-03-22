<?php
// admin/includes/header.php
// PRODUCTION v4.5 - Responsive Neon-Tech Sidebar & Layout Wrapper

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
        }
        
        /* Webkit Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #00f0ff; }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }

        /* Animations */
        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 0 10px rgba(0, 240, 255, 0.4); }
            50% { box-shadow: 0 0 20px rgba(0, 240, 255, 0.8); }
        }
        .animate-pulse-glow { animation: pulse-glow 2s infinite; }
        
        .fade-in { animation: fadeIn 0.3s ease-in-out; }
        .animate-fade-in-down { animation: fadeInDown 0.4s ease-out forwards; }
        
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes fadeInDown { 
            from { opacity: 0; transform: translateY(-10px); } 
            to { opacity: 1; transform: translateY(0); } 
        }

        /* Sidebar Transition */
        #adminSidebar { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    </style>
</head>
<body class="flex bg-[#0f172a] min-h-screen relative selection:bg-[#00f0ff]/30 selection:text-[#00f0ff]">

    <!-- Global Background FX -->
    <div class="fixed inset-0 w-full h-full -z-20 pointer-events-none">
        <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-blue-600/10 rounded-full blur-[120px]"></div>
        <div class="absolute bottom-0 left-0 w-[500px] h-[500px] bg-purple-600/10 rounded-full blur-[120px]"></div>
    </div>

    <!-- Mobile Header (Visible only on < 1024px) -->
    <div class="lg:hidden fixed top-0 left-0 right-0 h-16 bg-slate-900/90 backdrop-blur-md border-b border-slate-800 z-40 flex items-center justify-between px-4 shadow-lg">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-slate-800 flex items-center justify-center border border-[#00f0ff]/30 shadow-[0_0_10px_rgba(0,240,255,0.2)]">
                <i class="fas fa-bolt text-[#00f0ff]"></i>
            </div>
            <span class="font-black text-white text-lg tracking-tight">DMMM <span class="text-[#00f0ff]">Admin</span></span>
        </div>
        <button id="mobileMenuBtn" class="w-10 h-10 rounded-lg bg-slate-800 text-slate-300 flex items-center justify-center border border-slate-700 hover:text-white transition focus:outline-none">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <!-- Mobile Overlay -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-40 hidden lg:hidden transition-opacity opacity-0"></div>

    <!-- SIDEBAR -->
    <aside id="adminSidebar" class="fixed inset-y-0 left-0 w-64 bg-slate-900/95 backdrop-blur-xl border-r border-slate-800/80 z-50 transform -translate-x-full lg:translate-x-0 lg:static lg:flex shrink-0 flex-col shadow-[20px_0_50px_rgba(0,0,0,0.5)]">
        
        <!-- Brand Header -->
        <div class="h-20 flex items-center gap-3 px-6 border-b border-slate-800/80 shrink-0 relative overflow-hidden group">
            <div class="absolute inset-0 bg-gradient-to-r from-blue-600/10 to-[#00f0ff]/5 opacity-0 group-hover:opacity-100 transition duration-500 pointer-events-none"></div>
            
            <div class="w-10 h-10 rounded-xl bg-slate-950 flex items-center justify-center border border-[#00f0ff]/50 shadow-[0_0_15px_rgba(0,240,255,0.3)] relative z-10">
                <i class="fas fa-bolt text-[#00f0ff] text-lg"></i>
            </div>
            <div class="flex flex-col relative z-10">
                <span class="font-black text-white text-xl tracking-tight leading-none">DMMM</span>
                <span class="text-[9px] text-[#00f0ff] uppercase tracking-[0.2em] font-bold mt-0.5">Command Node</span>
            </div>

            <!-- Mobile Close Btn inside sidebar -->
            <button id="closeSidebarBtn" class="lg:hidden absolute right-4 text-slate-500 hover:text-white transition">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <!-- Identity Banner -->
        <div class="p-5 border-b border-slate-800/80 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-slate-700 to-slate-800 border-2 border-slate-600 flex items-center justify-center text-white font-bold shadow-inner relative">
                    <?php echo strtoupper(substr($_SESSION['admin_role'] ?? 'A', 0, 1)); ?>
                    <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-green-500 border-2 border-slate-900 rounded-full"></span>
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-bold text-white truncate">Operative <?php echo $_SESSION['admin_id'] ?? '1'; ?></p>
                    <p class="text-[10px] font-mono text-[#00f0ff] uppercase tracking-wider truncate">
                        <?php echo str_replace('_', ' ', $_SESSION['admin_role'] ?? 'ADMIN'); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Navigation Links -->
        <nav class="flex-1 overflow-y-auto custom-scrollbar py-4 space-y-1">
            
            <!-- Core -->
            <div class="px-6 mb-2 mt-2">
                <span class="text-[10px] uppercase font-black text-slate-500 tracking-[0.15em]">Core Systems</span>
            </div>
            
            <a href="<?php echo admin_url('dashboard'); ?>" class="mx-3 px-4 py-2.5 rounded-xl flex items-center gap-3 transition-all duration-200 <?php echo is_active('dashboard', $current_page) ? 'bg-[#00f0ff]/10 text-[#00f0ff] border-l-2 border-[#00f0ff] shadow-inner' : 'text-slate-400 hover:text-white hover:bg-slate-800 border-l-2 border-transparent'; ?>">
                <i class="fas fa-tachometer-alt w-5 text-center"></i>
                <span class="text-sm font-semibold tracking-wide">Dashboard</span>
            </a>

            <!-- Catalog -->
            <div class="px-6 mb-2 mt-6">
                <span class="text-[10px] uppercase font-black text-slate-500 tracking-[0.15em]">Catalog Matrix</span>
            </div>

            <a href="<?php echo admin_url('categories'); ?>" class="mx-3 px-4 py-2.5 rounded-xl flex items-center gap-3 transition-all duration-200 <?php echo is_active('categories', $current_page) ? 'bg-[#00f0ff]/10 text-[#00f0ff] border-l-2 border-[#00f0ff] shadow-inner' : 'text-slate-400 hover:text-white hover:bg-slate-800 border-l-2 border-transparent'; ?>">
                <i class="fas fa-network-wired w-5 text-center"></i>
                <span class="text-sm font-semibold tracking-wide">Sectors (Categories)</span>
            </a>
            <a href="<?php echo admin_url('products'); ?>" class="mx-3 px-4 py-2.5 rounded-xl flex items-center gap-3 transition-all duration-200 <?php echo is_active('products', $current_page) ? 'bg-[#00f0ff]/10 text-[#00f0ff] border-l-2 border-[#00f0ff] shadow-inner' : 'text-slate-400 hover:text-white hover:bg-slate-800 border-l-2 border-transparent'; ?>">
                <i class="fas fa-boxes w-5 text-center"></i>
                <span class="text-sm font-semibold tracking-wide">Digital Assets</span>
            </a>

            <!-- Operations -->
            <div class="px-6 mb-2 mt-6">
                <span class="text-[10px] uppercase font-black text-slate-500 tracking-[0.15em]">Operations</span>
            </div>

            <a href="<?php echo admin_url('orders'); ?>" class="mx-3 px-4 py-2.5 rounded-xl flex items-center justify-between transition-all duration-200 <?php echo is_active('orders', $current_page) ? 'bg-[#00f0ff]/10 text-[#00f0ff] border-l-2 border-[#00f0ff] shadow-inner' : 'text-slate-400 hover:text-white hover:bg-slate-800 border-l-2 border-transparent'; ?>">
                <div class="flex items-center gap-3">
                    <i class="fas fa-shopping-cart w-5 text-center"></i>
                    <span class="text-sm font-semibold tracking-wide">Orders & Comms</span>
                </div>
                <!-- Dynamic Pending Badge (Optional if you calculate it) -->
                <span class="w-2 h-2 rounded-full bg-yellow-500 animate-pulse shadow-[0_0_5px_#eab308]"></span>
            </a>
            <a href="<?php echo admin_url('users'); ?>" class="mx-3 px-4 py-2.5 rounded-xl flex items-center gap-3 transition-all duration-200 <?php echo is_active('users', $current_page) ? 'bg-[#00f0ff]/10 text-[#00f0ff] border-l-2 border-[#00f0ff] shadow-inner' : 'text-slate-400 hover:text-white hover:bg-slate-800 border-l-2 border-transparent'; ?>">
                <i class="fas fa-users w-5 text-center"></i>
                <span class="text-sm font-semibold tracking-wide">Identity Records</span>
            </a>
            <a href="<?php echo admin_url('reviews'); ?>" class="mx-3 px-4 py-2.5 rounded-xl flex items-center gap-3 transition-all duration-200 <?php echo is_active('reviews', $current_page) ? 'bg-[#00f0ff]/10 text-[#00f0ff] border-l-2 border-[#00f0ff] shadow-inner' : 'text-slate-400 hover:text-white hover:bg-slate-800 border-l-2 border-transparent'; ?>">
                <i class="fas fa-star w-5 text-center"></i>
                <span class="text-sm font-semibold tracking-wide">User Feedback</span>
            </a>

            <!-- Financials & Marketing -->
            <div class="px-6 mb-2 mt-6">
                <span class="text-[10px] uppercase font-black text-slate-500 tracking-[0.15em]">Financial & Growth</span>
            </div>

            <a href="<?php echo admin_url('pandl'); ?>" class="mx-3 px-4 py-2.5 rounded-xl flex items-center gap-3 transition-all duration-200 <?php echo is_active('pandl', $current_page) ? 'bg-green-500/10 text-green-400 border-l-2 border-green-500 shadow-inner' : 'text-slate-400 hover:text-white hover:bg-slate-800 border-l-2 border-transparent'; ?>">
                <i class="fas fa-chart-line w-5 text-center"></i>
                <span class="text-sm font-semibold tracking-wide">Profit Matrix (P&L)</span>
            </a>
            <a href="<?php echo admin_url('reports'); ?>" class="mx-3 px-4 py-2.5 rounded-xl flex items-center gap-3 transition-all duration-200 <?php echo is_active('reports', $current_page) ? 'bg-[#00f0ff]/10 text-[#00f0ff] border-l-2 border-[#00f0ff] shadow-inner' : 'text-slate-400 hover:text-white hover:bg-slate-800 border-l-2 border-transparent'; ?>">
                <i class="fas fa-file-invoice-dollar w-5 text-center"></i>
                <span class="text-sm font-semibold tracking-wide">Expense Logs</span>
            </a>
            <a href="<?php echo admin_url('passes'); ?>" class="mx-3 px-4 py-2.5 rounded-xl flex items-center gap-3 transition-all duration-200 <?php echo is_active('passes', $current_page) ? 'bg-yellow-500/10 text-yellow-400 border-l-2 border-yellow-500 shadow-inner' : 'text-slate-400 hover:text-white hover:bg-slate-800 border-l-2 border-transparent'; ?>">
                <i class="fas fa-crown w-5 text-center"></i>
                <span class="text-sm font-semibold tracking-wide">Agent Tiers</span>
            </a>
            <a href="<?php echo admin_url('coupons'); ?>" class="mx-3 px-4 py-2.5 rounded-xl flex items-center gap-3 transition-all duration-200 <?php echo is_active('coupons', $current_page) ? 'bg-purple-500/10 text-purple-400 border-l-2 border-purple-500 shadow-inner' : 'text-slate-400 hover:text-white hover:bg-slate-800 border-l-2 border-transparent'; ?>">
                <i class="fas fa-ticket-alt w-5 text-center"></i>
                <span class="text-sm font-semibold tracking-wide">Promo Codes</span>
            </a>
            <a href="<?php echo admin_url('banners'); ?>" class="mx-3 px-4 py-2.5 rounded-xl flex items-center gap-3 transition-all duration-200 <?php echo is_active('banners', $current_page) ? 'bg-[#00f0ff]/10 text-[#00f0ff] border-l-2 border-[#00f0ff] shadow-inner' : 'text-slate-400 hover:text-white hover:bg-slate-800 border-l-2 border-transparent'; ?>">
                <i class="fas fa-images w-5 text-center"></i>
                <span class="text-sm font-semibold tracking-wide">Frontend Banners</span>
            </a>

            <!-- System -->
            <div class="px-6 mb-2 mt-6">
                <span class="text-[10px] uppercase font-black text-slate-500 tracking-[0.15em]">System</span>
            </div>

            <a href="<?php echo admin_url('payments'); ?>" class="mx-3 px-4 py-2.5 rounded-xl flex items-center gap-3 transition-all duration-200 <?php echo is_active('payments', $current_page) ? 'bg-[#00f0ff]/10 text-[#00f0ff] border-l-2 border-[#00f0ff] shadow-inner' : 'text-slate-400 hover:text-white hover:bg-slate-800 border-l-2 border-transparent'; ?>">
                <i class="fas fa-wallet w-5 text-center"></i>
                <span class="text-sm font-semibold tracking-wide">Payment Nodes</span>
            </a>
            
            <?php if ($_SESSION['admin_role'] === 'super_admin'): ?>
            <a href="<?php echo admin_url('admins'); ?>" class="mx-3 px-4 py-2.5 rounded-xl flex items-center gap-3 transition-all duration-200 <?php echo is_active('admins', $current_page) ? 'bg-[#00f0ff]/10 text-[#00f0ff] border-l-2 border-[#00f0ff] shadow-inner' : 'text-slate-400 hover:text-white hover:bg-slate-800 border-l-2 border-transparent'; ?>">
                <i class="fas fa-user-shield w-5 text-center"></i>
                <span class="text-sm font-semibold tracking-wide">Staff Roster</span>
            </a>
            <?php endif; ?>

            <a href="<?php echo admin_url('settings'); ?>" class="mx-3 px-4 py-2.5 rounded-xl flex items-center gap-3 transition-all duration-200 <?php echo is_active('settings', $current_page) ? 'bg-[#00f0ff]/10 text-[#00f0ff] border-l-2 border-[#00f0ff] shadow-inner' : 'text-slate-400 hover:text-white hover:bg-slate-800 border-l-2 border-transparent'; ?>">
                <i class="fas fa-cogs w-5 text-center"></i>
                <span class="text-sm font-semibold tracking-wide">Settings</span>
            </a>

            <div class="h-6"></div> <!-- Bottom Spacer -->
        </nav>

        <!-- Logout Action -->
        <div class="p-4 border-t border-slate-800/80 bg-slate-900 shrink-0">
            <a href="logout.php" class="flex items-center justify-center gap-2 w-full bg-red-500/10 hover:bg-red-500/20 text-red-400 hover:text-red-300 font-bold py-2.5 rounded-xl border border-red-500/20 transition-colors shadow-inner text-sm uppercase tracking-wider group">
                <i class="fas fa-power-off group-hover:scale-110 transition-transform"></i> Terminate
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT WRAPPER -->
    <main class="flex-1 flex flex-col min-h-screen w-full lg:w-[calc(100%-16rem)] pt-16 lg:pt-0 overflow-x-hidden relative z-10 transition-all duration-300">
        
        <!-- Top Gradient Glow (Desktop) -->
        <div class="hidden lg:block absolute top-0 left-0 w-full h-32 bg-gradient-to-b from-slate-800/30 to-transparent pointer-events-none"></div>

        <!-- Content Area: The actual page content will be injected here by admin/index.php -->
        <div class="flex-1 p-4 sm:p-6 lg:p-8 animate-fade-in-down max-w-[1600px] w-full mx-auto">
            <!-- Child pages drop into here -->

            <!-- Mobile UI / Sidebar JS -->
            <script>
                document.addEventListener('DOMContentLoaded', () => {
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
                            }, 300);
                            document.body.style.overflow = '';
                        } else {
                            // Open
                            overlay.classList.remove('hidden');
                            // Small delay to allow display:block to apply before opacity transition
                            setTimeout(() => {
                                overlay.classList.remove('opacity-0');
                                sidebar.classList.remove('-translate-x-full');
                            }, 10);
                            document.body.style.overflow = 'hidden';
                        }
                    };

                    if (menuBtn) menuBtn.addEventListener('click', toggleSidebar);
                    if (closeBtn) closeBtn.addEventListener('click', toggleSidebar);
                    if (overlay) overlay.addEventListener('click', toggleSidebar);
                });
            </script>