<?php
// includes/header.php

// 1. Secure Session Start
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400 * 30, // 30 days
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'], // Dynamic domain
        'secure' => true, // Force HTTPS
        'httponly' => true, // Prevent JS access
        'samesite' => 'Strict'
    ]);
    session_start();
}

// 2. Helper for Active Link
function isActive($module, $page = null) {
    $c_mod = $_GET['module'] ?? 'home';
    $c_page = $_GET['page'] ?? 'index';
    if ($page) return ($c_mod == $module && $c_page == $page) ? 'text-[#00f0ff] font-bold drop-shadow-[0_0_8px_rgba(0,240,255,0.8)]' : 'text-gray-400 hover:text-white transition-colors';
    return ($c_mod == $module) ? 'text-[#00f0ff] font-bold drop-shadow-[0_0_8px_rgba(0,240,255,0.8)]' : 'text-gray-400 hover:text-white transition-colors';
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="description" content="DigitalMarketplaceMM - The #1 Premium Digital Store in Myanmar. Buy Game Keys, Software, and Subscriptions instantly via KBZPay/Wave.">
    <meta name="keywords" content="digital store, myanmar game shop, steam wallet mm, gift cards, premium accounts">
    <meta name="theme-color" content="#0f172a">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo BASE_URL; ?>">
    <meta property="og:title" content="DigitalMarketplaceMM - Premium Digital Store">
    <meta property="og:description" content="Instant delivery for Game Keys, Software, and Premium Accounts in Myanmar.">
    <meta property="og:image" content="<?php echo BASE_URL; ?>assets/images/og-image.jpg">

    <title>DigitalMarketplaceMM | Premium Digital Store</title>
    
    <!-- CSS Dependencies -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">

    <!-- Inline Critical CSS -->
    <style>
        .glass { background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(16px); border-bottom: 1px solid rgba(0, 240, 255, 0.1); }
        body { background-color: #0f172a; color: #f3f4f6; font-family: 'Inter', sans-serif; }
        
        /* Google Translate Hiding */
        .goog-te-banner-frame { display: none !important; }
        body { top: 0 !important; }
        .goog-te-gadget-simple { background-color: #1e293b !important; border: 1px solid #334155 !important; padding: 4px 8px !important; border-radius: 8px !important; }
        .goog-te-gadget-simple span { color: #e5e7eb !important; font-weight: 600 !important; }
        .goog-te-gadget-icon { display: none !important; }

        /* Custom Dropdown Animation */
        .dropdown-menu { transform-origin: top right; transition: all 0.2s ease-out; }
    </style>

    <!-- Google Translate API -->
    <script type="text/javascript">
        function googleTranslateElementInit() {
            new google.translate.TranslateElement({
                pageLanguage: 'en',
                includedLanguages: 'en,my',
                layout: google.translate.TranslateElement.InlineLayout.SIMPLE,
                autoDisplay: false
            }, 'google_translate_element');
        }
    </script>
    <script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
</head>
<body class="flex flex-col min-h-screen bg-[#0f172a] text-gray-100 antialiased selection:bg-[#00f0ff]/30 selection:text-[#00f0ff]">

    <!-- Navbar -->
    <nav class="glass sticky top-0 z-50 transition-all duration-300 shadow-[0_4px_30px_rgba(0,0,0,0.5)]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                
                <!-- 1. Logo Area -->
                <a href="index.php" class="flex items-center gap-3 group relative z-10">
                    <div class="w-10 h-10 bg-slate-800 rounded-xl flex items-center justify-center shadow-[0_0_15px_rgba(0,240,255,0.1)] group-hover:shadow-[0_0_25px_rgba(0,240,255,0.3)] transition duration-300 border border-[#00f0ff]/30 group-hover:border-[#00f0ff]">
                        <i class="fas fa-bolt text-[#00f0ff] text-lg animate-pulse-glow"></i>
                    </div>
                    <div class="flex flex-col">
                        <span class="font-bold text-xl leading-none tracking-tight text-white group-hover:text-[#00f0ff] transition">Digital<span class="text-[#00f0ff]">MM</span></span>
                        <span class="text-[9px] text-slate-400 uppercase tracking-[0.2em] font-bold mt-1">Marketplace</span>
                    </div>
                </a>

                <!-- 2. Desktop Search (Replaced with Interactive Trigger) -->
                <div class="hidden lg:flex flex-1 items-center justify-center px-12">
                    <div class="relative w-full max-w-xl group">
                        <button onclick="document.getElementById('search-modal').classList.remove('hidden')" class="w-full flex items-center justify-between bg-slate-900/50 border border-slate-700 hover:border-[#00f0ff]/50 rounded-xl py-2.5 px-4 text-sm text-slate-400 transition-all duration-300 shadow-inner group-hover:shadow-[0_0_15px_rgba(0,240,255,0.05)]">
                            <span class="flex items-center gap-3">
                                <i class="fas fa-search text-slate-500 group-hover:text-[#00f0ff] transition"></i>
                                Search games, software, gifts...
                            </span>
                            <kbd class="hidden sm:inline-block border border-slate-600 bg-slate-800 rounded px-2 py-0.5 text-[10px] font-mono text-slate-400">Ctrl K</kbd>
                        </button>
                    </div>
                </div>

                <!-- 3. Right Toolbar -->
                <div class="hidden lg:flex items-center gap-5">
                    
                    <!-- Main Navigation Links -->
                    <div class="flex items-center gap-4 border-r border-slate-700 pr-5 mr-1">
                        <a href="index.php?module=shop&page=search" class="text-sm font-medium <?php echo isActive('shop', 'search'); ?>">Store</a>
                        <a href="index.php?module=user&page=agent" class="text-sm font-medium flex items-center gap-1.5 <?php echo isActive('user', 'agent'); ?>">
                            <i class="fas fa-crown text-yellow-500"></i> Reseller
                        </a>
                    </div>

                    <!-- Utilities (Lang + Currency) -->
                    <div class="flex items-center bg-slate-800/50 rounded-xl p-1 border border-slate-700/50 backdrop-blur-sm">
                        <div id="google_translate_element" class="mr-2 opacity-80 hover:opacity-100 transition"></div>
                        <div class="h-4 w-px bg-slate-700 mx-1"></div>
                        <a href="?set_curr=MMK" class="px-2.5 py-1 text-xs font-bold rounded-lg transition-all <?php echo (isset($_SESSION['currency']) && $_SESSION['currency']=='MMK') ? 'bg-[#00f0ff]/10 text-[#00f0ff] border border-[#00f0ff]/30' : 'text-slate-400 hover:text-white'; ?>">Ks</a>
                        <a href="?set_curr=USD" class="px-2.5 py-1 text-xs font-bold rounded-lg transition-all <?php echo (isset($_SESSION['currency']) && $_SESSION['currency']=='USD') ? 'bg-[#00f0ff]/10 text-[#00f0ff] border border-[#00f0ff]/30' : 'text-slate-400 hover:text-white'; ?>">$</a>
                    </div>

                    <!-- User Actions -->
                    <?php if(isset($_SESSION['user_id'])): ?>
                        
                        <!-- Wishlist Quick Link -->
                        <a href="index.php?module=user&page=wishlist" class="relative text-slate-400 hover:text-rose-400 transition" title="Wishlist">
                            <i class="fas fa-heart text-lg"></i>
                        </a>

                        <!-- Notifications -->
                        <div class="relative cursor-pointer text-slate-400 hover:text-[#00f0ff] transition group">
                            <div class="w-10 h-10 rounded-xl hover:bg-slate-800 flex items-center justify-center transition border border-transparent hover:border-slate-700">
                                <i class="far fa-bell text-lg"></i>
                            </div>
                            <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full animate-pulse border border-slate-900 hidden" id="nav-notif-badge"></span>
                            
                            <!-- Dropdown -->
                            <div class="absolute right-0 top-12 w-80 bg-slate-800/95 backdrop-blur-xl rounded-xl shadow-[0_10px_40px_rgba(0,0,0,0.5)] border border-slate-700 opacity-0 invisible group-hover:opacity-100 group-hover:visible dropdown-menu z-50">
                                <div class="px-4 py-3 border-b border-slate-700 flex justify-between items-center bg-slate-900/50 rounded-t-xl">
                                    <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Alerts</h4>
                                    <span class="text-[10px] text-[#00f0ff] cursor-pointer hover:underline">Mark read</span>
                                </div>
                                <div class="max-h-64 overflow-y-auto custom-scrollbar" id="nav-notif-list">
                                    <div class="text-xs text-center py-8 text-slate-500">
                                        <i class="fas fa-check-circle text-2xl mb-2 opacity-20"></i><br>All caught up
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Profile Dropdown -->
                        <div class="relative group">
                            <button class="flex items-center gap-3 focus:outline-none pl-2">
                                <div class="w-10 h-10 rounded-full bg-slate-800 p-0.5 shadow-lg border border-[#00f0ff]/30 group-hover:border-[#00f0ff] transition-all relative">
                                    <div class="w-full h-full rounded-full bg-slate-900 flex items-center justify-center text-white font-bold text-sm">
                                        <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
                                    </div>
                                    <div class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-green-500 border-2 border-slate-900 rounded-full"></div>
                                </div>
                            </button>
                            
                            <div class="absolute right-0 top-14 w-64 bg-slate-800/95 backdrop-blur-xl rounded-xl shadow-[0_10px_40px_rgba(0,0,0,0.5)] border border-slate-700 opacity-0 invisible group-hover:opacity-100 group-hover:visible dropdown-menu z-50 overflow-hidden">
                                
                                <!-- User Identity Banner -->
                                <div class="px-5 py-4 border-b border-slate-700 bg-slate-900/50 relative overflow-hidden">
                                    <div class="absolute inset-0 bg-[#00f0ff]/5"></div>
                                    <div class="relative z-10">
                                        <p class="text-sm text-white font-bold truncate flex items-center gap-2">
                                            <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>
                                        </p>
                                        <p class="text-xs text-slate-400 truncate mt-0.5 font-mono"><?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?></p>
                                    </div>
                                </div>
                                
                                <div class="p-2 space-y-0.5">
                                    <a href="index.php?module=user&page=dashboard" class="block px-3 py-2 rounded-lg text-sm text-slate-300 hover:bg-slate-700 hover:text-white transition flex items-center gap-3 group/link">
                                        <i class="fas fa-chart-pie w-5 text-center text-slate-500 group-hover/link:text-[#00f0ff] transition-colors"></i> Dashboard
                                    </a>
                                    <a href="index.php?module=user&page=orders" class="block px-3 py-2 rounded-lg text-sm text-slate-300 hover:bg-slate-700 hover:text-white transition flex items-center gap-3 group/link">
                                        <i class="fas fa-box-open w-5 text-center text-slate-500 group-hover/link:text-green-400 transition-colors"></i> My Orders
                                    </a>
                                    <a href="index.php?module=user&page=wallet" class="block px-3 py-2 rounded-lg text-sm text-slate-300 hover:bg-slate-700 hover:text-white transition flex items-center gap-3 group/link">
                                        <i class="fas fa-wallet w-5 text-center text-slate-500 group-hover/link:text-purple-400 transition-colors"></i> Wallet
                                    </a>
                                    <a href="index.php?module=user&page=referrals" class="block px-3 py-2 rounded-lg text-sm text-slate-300 hover:bg-slate-700 hover:text-white transition flex items-center gap-3 group/link">
                                        <i class="fas fa-users w-5 text-center text-slate-500 group-hover/link:text-yellow-400 transition-colors"></i> Referrals
                                    </a>
                                    <a href="index.php?module=user&page=profile" class="block px-3 py-2 rounded-lg text-sm text-slate-300 hover:bg-slate-700 hover:text-white transition flex items-center gap-3 group/link">
                                        <i class="fas fa-cog w-5 text-center text-slate-500 group-hover/link:text-gray-300 transition-colors"></i> Settings
                                    </a>
                                </div>

                                <div class="border-t border-slate-700 p-2 bg-slate-900/30">
                                    <a href="index.php?module=auth&page=logout" class="block w-full px-3 py-2 rounded-lg text-xs font-bold text-red-400 hover:bg-red-500/10 hover:text-red-300 transition flex items-center justify-center gap-2">
                                        <i class="fas fa-power-off"></i> Secure Logout
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="flex items-center gap-3 ml-2">
                            <a href="index.php?module=auth&page=login" class="text-slate-300 hover:text-white font-medium text-sm transition px-4 py-2 rounded-lg hover:bg-slate-800 border border-transparent hover:border-slate-700">Login</a>
                            <a href="index.php?module=auth&page=register" class="bg-[#00f0ff]/10 hover:bg-[#00f0ff]/20 text-[#00f0ff] border border-[#00f0ff]/30 hover:border-[#00f0ff] px-5 py-2 rounded-xl font-bold text-sm shadow-[0_0_15px_rgba(0,240,255,0.1)] hover:shadow-[0_0_20px_rgba(0,240,255,0.2)] transition-all">Sign Up</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Mobile Menu Button -->
                <div class="lg:hidden flex items-center gap-4">
                    <button onclick="document.getElementById('search-modal').classList.remove('hidden')" class="text-slate-400 hover:text-[#00f0ff] transition focus:outline-none">
                        <i class="fas fa-search text-xl"></i>
                    </button>
                    <button onclick="document.getElementById('mobile-menu').classList.toggle('hidden')" class="text-slate-300 hover:text-white p-2 focus:outline-none rounded-lg hover:bg-slate-800 transition">
                        <i class="fas fa-bars text-2xl"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu Overlay -->
        <div id="mobile-menu" class="hidden lg:hidden bg-slate-900/95 backdrop-blur-2xl border-b border-slate-700 shadow-2xl absolute w-full z-40 transition-all origin-top">
            <div class="px-4 pt-4 pb-6 space-y-4">
                
                <div class="grid grid-cols-2 gap-3 mb-6">
                    <a href="index.php" class="flex flex-col items-center justify-center p-3 rounded-xl bg-slate-800 border border-slate-700 text-slate-300 hover:text-[#00f0ff] hover:border-[#00f0ff]/50 transition gap-2">
                        <i class="fas fa-home text-xl"></i>
                        <span class="text-xs font-bold uppercase">Home</span>
                    </a>
                    <a href="index.php?module=shop&page=search" class="flex flex-col items-center justify-center p-3 rounded-xl bg-slate-800 border border-slate-700 text-slate-300 hover:text-[#00f0ff] hover:border-[#00f0ff]/50 transition gap-2">
                        <i class="fas fa-store text-xl"></i>
                        <span class="text-xs font-bold uppercase">Store</span>
                    </a>
                    <a href="index.php?module=user&page=agent" class="flex flex-col items-center justify-center p-3 rounded-xl bg-yellow-500/10 border border-yellow-500/20 text-yellow-500 hover:bg-yellow-500/20 transition gap-2">
                        <i class="fas fa-crown text-xl"></i>
                        <span class="text-xs font-bold uppercase">Reseller</span>
                    </a>
                    <a href="index.php?module=info&page=support" class="flex flex-col items-center justify-center p-3 rounded-xl bg-slate-800 border border-slate-700 text-slate-300 hover:text-[#00f0ff] hover:border-[#00f0ff]/50 transition gap-2">
                        <i class="fas fa-headset text-xl"></i>
                        <span class="text-xs font-bold uppercase">Support</span>
                    </a>
                </div>

                <?php if(isset($_SESSION['user_id'])): ?>
                    <div class="border-t border-slate-700 pt-6">
                        <div class="flex items-center px-2 mb-4">
                            <div class="h-10 w-10 rounded-full bg-slate-800 border border-[#00f0ff]/50 flex items-center justify-center text-[#00f0ff] font-bold text-lg">
                                <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
                            </div>
                            <div class="ml-3">
                                <div class="text-sm font-bold text-white"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                                <div class="text-xs text-slate-400 font-mono"><?php echo htmlspecialchars($_SESSION['user_email']); ?></div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <a href="index.php?module=user&page=dashboard" class="px-3 py-2 rounded-lg text-sm font-medium text-slate-300 bg-slate-800/50 flex items-center gap-2"><i class="fas fa-chart-pie text-slate-500"></i> Dashboard</a>
                            <a href="index.php?module=user&page=orders" class="px-3 py-2 rounded-lg text-sm font-medium text-slate-300 bg-slate-800/50 flex items-center gap-2"><i class="fas fa-box text-slate-500"></i> Orders</a>
                            <a href="index.php?module=user&page=wallet" class="px-3 py-2 rounded-lg text-sm font-medium text-slate-300 bg-slate-800/50 flex items-center gap-2"><i class="fas fa-wallet text-slate-500"></i> Wallet</a>
                            <a href="index.php?module=user&page=wishlist" class="px-3 py-2 rounded-lg text-sm font-medium text-slate-300 bg-slate-800/50 flex items-center gap-2"><i class="fas fa-heart text-slate-500"></i> Wishlist</a>
                            <a href="index.php?module=auth&page=logout" class="col-span-2 mt-2 px-3 py-2.5 rounded-lg text-sm font-bold text-red-400 bg-red-500/10 flex justify-center items-center gap-2 border border-red-500/20"><i class="fas fa-power-off"></i> Logout</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-2 gap-4 border-t border-slate-700 pt-6">
                        <a href="index.php?module=auth&page=login" class="text-center px-4 py-3 border border-slate-600 rounded-xl text-slate-300 font-bold hover:bg-slate-800 transition">Login</a>
                        <a href="index.php?module=auth&page=register" class="text-center px-4 py-3 bg-[#00f0ff]/10 text-[#00f0ff] border border-[#00f0ff]/30 rounded-xl font-bold shadow-[0_0_15px_rgba(0,240,255,0.1)]">Sign Up</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Global Search Modal -->
    <div id="search-modal" class="fixed inset-0 z-[60] hidden">
        <div class="absolute inset-0 bg-slate-900/90 backdrop-blur-sm" onclick="document.getElementById('search-modal').classList.add('hidden')"></div>
        
        <div class="absolute top-20 left-1/2 transform -translate-x-1/2 w-full max-w-2xl px-4 animate-fade-in-down">
            <form action="index.php" method="GET" class="relative bg-slate-800 rounded-2xl shadow-[0_0_50px_rgba(0,0,0,0.5)] border border-slate-600 overflow-hidden flex items-center p-2 focus-within:border-[#00f0ff]/50 focus-within:shadow-[0_0_30px_rgba(0,240,255,0.15)] transition-all">
                <input type="hidden" name="module" value="shop">
                <input type="hidden" name="page" value="search">
                
                <div class="pl-4 pr-2 text-slate-500"><i class="fas fa-search text-xl"></i></div>
                <input type="text" name="q" placeholder="What are you looking for?" id="global-search-input"
                       class="flex-1 bg-transparent border-none py-3 px-2 text-white text-lg placeholder-slate-500 focus:outline-none focus:ring-0">
                
                <button type="submit" class="bg-blue-600 hover:bg-[#00f0ff] hover:text-slate-900 text-white px-6 py-3 rounded-xl font-bold transition-colors">Search</button>
            </form>
            <div class="text-center mt-3 text-xs text-slate-500">
                Press <kbd class="bg-slate-800 border border-slate-700 px-1.5 py-0.5 rounded text-slate-300">ESC</kbd> to close
            </div>
        </div>
    </div>

    <!-- Main Content Wrapper -->
    <main class="flex-grow relative z-0 pb-12">
        <!-- Abstract Background Effects for whole site -->
        <div class="absolute top-0 inset-x-0 h-[500px] overflow-hidden -z-10 pointer-events-none">
            <div class="absolute -top-40 -right-40 w-96 h-96 bg-blue-900/20 rounded-full blur-3xl opacity-50"></div>
            <div class="absolute top-20 -left-40 w-96 h-96 bg-purple-900/20 rounded-full blur-3xl opacity-30"></div>
        </div>

    <script>
        // Keyboard shortcuts for search modal
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                const modal = document.getElementById('search-modal');
                modal.classList.remove('hidden');
                setTimeout(() => document.getElementById('global-search-input').focus(), 100);
            }
            if (e.key === 'Escape') {
                document.getElementById('search-modal').classList.add('hidden');
            }
        });
    </script>