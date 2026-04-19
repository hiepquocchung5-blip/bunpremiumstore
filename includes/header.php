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
    if ($page) return ($c_mod == $module && $c_page == $page) ? 'text-[#00f0ff] font-bold drop-shadow-[0_0_8px_rgba(0,240,255,0.8)]' : 'text-slate-400 hover:text-white transition-colors';
    return ($c_mod == $module) ? 'text-[#00f0ff] font-bold drop-shadow-[0_0_8px_rgba(0,240,255,0.8)]' : 'text-slate-400 hover:text-white transition-colors';
}

// 3. Detect Current Language from Google Translate Cookie
$curr_lang = 'en';
if(isset($_COOKIE['googtrans'])) {
    if(strpos($_COOKIE['googtrans'], '/my') !== false) {
        $curr_lang = 'my';
    }
}
$lang_emoji = $curr_lang == 'my' ? '🇲🇲' : '🇺🇸';
$lang_text = $curr_lang == 'my' ? 'MY' : 'EN';

// 4. Current Currency
$curr_currency = $_SESSION['currency'] ?? 'MMK';
$curr_symbol = $curr_currency == 'USD' ? '$' : 'Ks';

// 5. Auto-Heal Database Schema (Fixes Push Subscription Error)
global $pdo;
if (isset($pdo)) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS push_subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            endpoint VARCHAR(500) NOT NULL UNIQUE,
            p256dh VARCHAR(255) NOT NULL,
            auth VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
    } catch (PDOException $e) {
        // Fails gracefully
    }
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
    <meta property="og:url" content="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>">
    <meta property="og:title" content="DigitalMarketplaceMM - Premium Digital Store">
    <meta property="og:description" content="Instant delivery for Game Keys, Software, and Premium Accounts in Myanmar.">
    <meta property="og:image" content="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>assets/images/og-image.jpg">

    <title>DigitalMarketplaceMM | Premium Digital Store</title>
    
    <!-- CSS Dependencies -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">

    <!-- Inline Critical CSS -->
    <style>
        .glass { background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border-bottom: 1px solid rgba(0, 240, 255, 0.1); }
        .glass-pill { background: rgba(15, 23, 42, 0.90); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid rgba(0, 240, 255, 0.2); }
        body { background-color: #0f172a; color: #f3f4f6; font-family: 'Inter', sans-serif; -webkit-tap-highlight-color: transparent; }
        
        /* Completely Hide Google Translate Default UI */
        .goog-te-banner-frame, .skiptranslate, #google_translate_element { display: none !important; }
        body { top: 0 !important; }

        /* Custom Dropdown Animation */
        .dropdown-menu { transform-origin: top right; transition: all 0.2s ease-out; }
        
        /* Bottom Nav Safe Area */
        main { padding-bottom: 90px; }
        @media (min-width: 1024px) { main { padding-bottom: 0; } }
    </style>

    <!-- Google Translate API (Hidden but functional) -->
    <script type="text/javascript">
        function googleTranslateElementInit() {
            new google.translate.TranslateElement({
                pageLanguage: 'en',
                includedLanguages: 'en,my',
                autoDisplay: false
            }, 'google_translate_element');
        }
        
        function changeLanguage(lang) {
            document.cookie = `googtrans=/en/${lang}; path=/`;
            document.cookie = `googtrans=/en/${lang}; domain=.${location.hostname}; path=/`;
            window.location.reload();
        }
    </script>
    <script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>

    <!-- Global App Configuration (Injected into Head to prevent JS Race Conditions) -->
    <script>
        window.AppConfig = {
            vapidPublicKey: "<?php echo $_ENV['VAPID_PUBLIC_KEY'] ?? ''; ?>",
            baseUrl: "<?php echo defined('BASE_URL') ? BASE_URL : '/'; ?>"
        };
    </script>
</head>
<body class="flex flex-col min-h-screen bg-[#0f172a] text-gray-100 antialiased selection:bg-[#00f0ff]/30 selection:text-[#00f0ff]">

    <!-- Hidden translate element for API to hook into -->
    <div id="google_translate_element"></div>

    <!-- Top Navbar -->
    <nav class="glass sticky top-0 z-50 transition-all duration-300 shadow-[0_4px_30px_rgba(0,0,0,0.5)]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 lg:h-20">
                
                <!-- 1. Logo Area -->
                <a href="index.php" class="flex items-center gap-3 group relative z-10 shrink-0">
                    <div class="w-10 h-10 lg:w-12 lg:h-12 bg-slate-800 rounded-xl flex items-center justify-center shadow-[0_0_15px_rgba(0,240,255,0.1)] group-hover:shadow-[0_0_25px_rgba(0,240,255,0.3)] transition duration-300 border border-[#00f0ff]/30 group-hover:border-[#00f0ff] overflow-hidden">
                        <img src="assets/images/logo.png" alt="DMMM" class="w-full h-full object-contain p-1 transform group-hover:scale-110 transition-transform duration-300" onerror="this.outerHTML='<i class=\'fas fa-bolt text-[#00f0ff] text-xl\'></i>'">
                    </div>
                    <div class="flex flex-col">
                        <span class="font-black text-lg lg:text-xl leading-none tracking-tight text-white group-hover:text-[#00f0ff] transition">Digital<span class="text-[#00f0ff]">MM</span></span>
                        <span class="text-[8px] lg:text-[9px] text-slate-400 uppercase tracking-[0.2em] font-bold mt-0.5">Marketplace</span>
                    </div>
                </a>

                <!-- 2. Desktop Search -->
                <div class="hidden lg:flex flex-1 items-center justify-center px-12">
                    <div class="relative w-full max-w-xl group">
                        <button onclick="document.getElementById('search-modal').classList.remove('hidden')" class="w-full flex items-center justify-between bg-slate-900/50 border border-slate-700 hover:border-[#00f0ff]/50 rounded-xl py-2.5 px-4 text-sm text-slate-400 transition-all duration-300 shadow-inner group-hover:shadow-[0_0_15px_rgba(0,240,255,0.05)]">
                            <span class="flex items-center gap-3">
                                <i class="fas fa-search text-slate-500 group-hover:text-[#00f0ff] transition"></i>
                                Search games, software, gifts...
                            </span>
                            <kbd class="border border-slate-600 bg-slate-800 rounded px-2 py-0.5 text-[10px] font-mono text-slate-400">Ctrl K</kbd>
                        </button>
                    </div>
                </div>

                <!-- 3. Right Toolbar (Desktop & Mobile Mixed) -->
                <div class="flex items-center gap-3 lg:gap-5 relative z-10">
                    
                    <!-- NEW: Smart Push Uplink Button -->
                    <div class="enable-push-wrapper hidden sm:block">
                        <button class="enable-push-btn flex items-center gap-2 bg-[#00f0ff]/10 border border-[#00f0ff]/30 hover:bg-[#00f0ff]/20 text-[#00f0ff] px-3 py-2 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all shadow-[0_0_10px_rgba(0,240,255,0.1)]">
                            <i class="fas fa-satellite-dish animate-pulse"></i> Connect
                        </button>
                    </div>

                    <!-- Desktop Main Navigation Links -->
                    <div class="hidden lg:flex items-center gap-4 border-r border-slate-700 pr-5 mr-1">
                        <a href="index.php?module=shop&page=search" class="text-sm font-bold uppercase tracking-wider <?php echo isActive('shop', 'search'); ?>">Store</a>
                        <a href="index.php?module=user&page=agent" class="text-sm font-bold uppercase tracking-wider flex items-center gap-1.5 <?php echo isActive('user', 'agent'); ?>">
                            <i class="fas fa-crown text-yellow-500"></i> Reseller
                        </a>
                    </div>

                    <!-- MATRIX LOCALIZATION DROPDOWN (Upgraded UI) -->
                    <div class="relative group">
                        <button class="flex items-center gap-2 bg-slate-900/80 border border-slate-700 hover:border-[#00f0ff]/50 rounded-xl px-2.5 py-1.5 lg:px-3 lg:py-2 transition-all duration-300 shadow-[inset_0_0_10px_rgba(0,0,0,0.3)] group-hover:shadow-[0_0_15px_rgba(0,240,255,0.2)] focus:outline-none">
                            <i class="fas fa-globe text-slate-400 group-hover:text-[#00f0ff] transition-colors"></i>
                            <span class="text-xs font-bold text-white hidden sm:inline"><?php echo $lang_text; ?> <span class="text-slate-600 mx-1">|</span> <span class="text-[#00f0ff]"><?php echo $curr_symbol; ?></span></span>
                        </button>
                        
                        <!-- Dropdown Menu -->
                        <div class="absolute right-0 top-full mt-2 w-56 bg-slate-900/95 backdrop-blur-xl rounded-xl shadow-[0_20px_50px_rgba(0,0,0,0.7)] border border-slate-700 opacity-0 invisible group-hover:opacity-100 group-hover:visible dropdown-menu z-50 overflow-hidden">
                            
                            <div class="absolute -right-10 -top-10 w-32 h-32 bg-[#00f0ff]/10 rounded-full blur-3xl pointer-events-none"></div>

                            <div class="px-4 py-2.5 bg-slate-800/50 border-b border-slate-700/50 flex items-center gap-2">
                                <i class="fas fa-language text-[#00f0ff] text-xs"></i>
                                <span class="text-[10px] font-black text-slate-300 uppercase tracking-widest">Translation Protocol</span>
                            </div>
                            <div class="p-2 space-y-1 relative z-10">
                                <button onclick="changeLanguage('en')" class="w-full text-left px-3 py-2 text-sm text-slate-300 hover:text-white hover:bg-slate-800 rounded-lg flex items-center justify-between transition group/lang">
                                    <span class="flex items-center gap-2"><span class="text-lg">🇺🇸</span> English</span>
                                    <?php if($curr_lang=='en') echo '<i class="fas fa-check text-green-400 text-xs shadow-[0_0_10px_#4ade80]"></i>'; ?>
                                </button>
                                <button onclick="changeLanguage('my')" class="w-full text-left px-3 py-2 text-sm text-slate-300 hover:text-white hover:bg-slate-800 rounded-lg flex items-center justify-between transition group/lang">
                                    <span class="flex items-center gap-2"><span class="text-lg">🇲🇲</span> Myanmar</span>
                                    <?php if($curr_lang=='my') echo '<i class="fas fa-check text-green-400 text-xs shadow-[0_0_10px_#4ade80]"></i>'; ?>
                                </button>
                            </div>
                            
                            <div class="px-4 py-2.5 bg-slate-800/50 border-y border-slate-700/50 flex items-center gap-2">
                                <i class="fas fa-coins text-yellow-400 text-xs"></i>
                                <span class="text-[10px] font-black text-slate-300 uppercase tracking-widest">Currency Matrix</span>
                            </div>
                            <div class="p-2 space-y-1 relative z-10">
                                <a href="?set_curr=MMK" class="w-full text-left px-3 py-2 text-sm text-slate-300 hover:text-white hover:bg-slate-800 rounded-lg flex items-center justify-between transition group/curr">
                                    <span class="font-bold tracking-wide">MMK <span class="text-slate-500 font-normal">(Kyat)</span></span>
                                    <?php if($curr_currency=='MMK') echo '<i class="fas fa-check text-green-400 text-xs shadow-[0_0_10px_#4ade80]"></i>'; ?>
                                </a>
                                <a href="?set_curr=USD" class="w-full text-left px-3 py-2 text-sm text-slate-300 hover:text-white hover:bg-slate-800 rounded-lg flex items-center justify-between transition group/curr">
                                    <span class="font-bold tracking-wide">USD <span class="text-slate-500 font-normal">($)</span></span>
                                    <?php if($curr_currency=='USD') echo '<i class="fas fa-check text-green-400 text-xs shadow-[0_0_10px_#4ade80]"></i>'; ?>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- User Actions (Desktop Only & Unified Notification) -->
                    <?php if(isset($_SESSION['user_id'])): ?>
                        
                        <!-- Notifications -->
                        <div class="relative cursor-pointer text-slate-400 hover:text-[#00f0ff] transition group hidden sm:block">
                            <div class="w-9 h-9 lg:w-10 lg:h-10 rounded-xl hover:bg-slate-800 flex items-center justify-center transition border border-transparent hover:border-slate-700">
                                <i class="far fa-bell text-lg"></i>
                            </div>
                            <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full animate-pulse border border-slate-900 hidden" id="nav-notif-badge"></span>
                            
                            <div class="absolute right-0 top-full mt-2 w-72 lg:w-80 bg-slate-800/95 backdrop-blur-xl rounded-xl shadow-[0_10px_40px_rgba(0,0,0,0.5)] border border-slate-700 opacity-0 invisible group-hover:opacity-100 group-hover:visible dropdown-menu z-50">
                                <div class="px-4 py-3 border-b border-slate-700 flex justify-between items-center bg-slate-900/50 rounded-t-xl">
                                    <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">System Alerts</h4>
                                    <span class="text-[10px] text-[#00f0ff] cursor-pointer hover:underline">Mark read</span>
                                </div>
                                <div class="max-h-64 overflow-y-auto custom-scrollbar" id="nav-notif-list">
                                    <div class="text-xs text-center py-8 text-slate-500">
                                        <i class="fas fa-check-circle text-2xl mb-2 opacity-20"></i><br>All caught up
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Profile Dropdown (Desktop Only) -->
                        <div class="relative group hidden lg:block">
                            <button class="flex items-center gap-3 focus:outline-none pl-2">
                                <div class="w-10 h-10 rounded-full bg-slate-800 p-0.5 shadow-lg border border-[#00f0ff]/30 group-hover:border-[#00f0ff] transition-all relative">
                                    <div class="w-full h-full rounded-full bg-slate-900 flex items-center justify-center text-white font-bold text-sm">
                                        <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
                                    </div>
                                    <div class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-green-500 border-2 border-slate-900 rounded-full"></div>
                                </div>
                            </button>
                            
                            <div class="absolute right-0 top-full mt-2 w-64 bg-slate-800/95 backdrop-blur-xl rounded-xl shadow-[0_10px_40px_rgba(0,0,0,0.5)] border border-slate-700 opacity-0 invisible group-hover:opacity-100 group-hover:visible dropdown-menu z-50 overflow-hidden">
                                
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
                                    <a href="index.php?module=user&page=wishlist" class="block px-3 py-2 rounded-lg text-sm text-slate-300 hover:bg-slate-700 hover:text-white transition flex items-center gap-3 group/link">
                                        <i class="fas fa-heart w-5 text-center text-slate-500 group-hover/link:text-rose-400 transition-colors"></i> Wishlist
                                    </a>
                                    <a href="index.php?module=user&page=wallet" class="block px-3 py-2 rounded-lg text-sm text-slate-300 hover:bg-slate-700 hover:text-white transition flex items-center gap-3 group/link">
                                        <i class="fas fa-wallet w-5 text-center text-slate-500 group-hover/link:text-purple-400 transition-colors"></i> Wallet
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
                        <div class="hidden lg:flex items-center gap-3 ml-2">
                            <a href="index.php?module=auth&page=login" class="text-slate-300 hover:text-white font-medium text-sm transition px-4 py-2 rounded-lg hover:bg-slate-800 border border-transparent hover:border-slate-700">Login</a>
                            <a href="index.php?module=auth&page=register" class="bg-gradient-to-r from-blue-600 to-[#00f0ff] hover:from-blue-500 hover:to-[#00f0ff] text-slate-900 px-5 py-2 rounded-xl font-black text-sm shadow-[0_0_15px_rgba(0,240,255,0.3)] transition-all">Sign Up</a>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </nav>

    <!-- ========================================== -->
    <!-- FLOATING APP BOTTOM NAV (MOBILE ONLY)      -->
    <!-- ========================================== -->
    <div class="fixed bottom-4 left-4 right-4 z-50 lg:hidden pointer-events-none">
        <div class="glass-pill rounded-2xl px-6 py-3 flex justify-between items-center shadow-[0_15px_40px_rgba(0,0,0,0.8)] pointer-events-auto relative">
            
            <a href="index.php" class="flex flex-col items-center gap-1.5 <?php echo isActive('home'); ?>">
                <i class="fas fa-home text-lg"></i>
                <span class="text-[8px] font-black uppercase tracking-widest">Home</span>
            </a>

            <button onclick="document.getElementById('search-modal').classList.remove('hidden')" class="flex flex-col items-center gap-1.5 text-slate-400 hover:text-[#00f0ff] transition">
                <i class="fas fa-search text-lg"></i>
                <span class="text-[8px] font-black uppercase tracking-widest">Search</span>
            </button>

            <!-- Center Action Button -->
            <div class="relative -top-6">
                <a href="index.php?module=shop&page=category" class="w-14 h-14 bg-gradient-to-br from-blue-600 to-[#00f0ff] text-slate-900 rounded-full flex items-center justify-center shadow-[0_0_20px_rgba(0,240,255,0.4)] border-4 border-slate-900 transform transition active:scale-95">
                    <i class="fas fa-layer-group text-xl"></i>
                </a>
            </div>

            <a href="index.php?module=user&page=orders" class="flex flex-col items-center gap-1.5 <?php echo isActive('user', 'orders'); ?>">
                <i class="fas fa-box-open text-lg"></i>
                <span class="text-[8px] font-black uppercase tracking-widest">Orders</span>
            </a>

            <!-- Toggle Mobile Menu Overlay -->
            <button onclick="document.getElementById('mobile-menu').classList.toggle('translate-y-full'); document.getElementById('mobile-menu').classList.toggle('opacity-0');" class="flex flex-col items-center gap-1.5 text-slate-400 hover:text-[#00f0ff] transition">
                <i class="fas fa-bars text-lg"></i>
                <span class="text-[8px] font-black uppercase tracking-widest">Menu</span>
            </button>

        </div>
    </div>

    <!-- Mobile Menu Overlay (Slides up from bottom) -->
    <div id="mobile-menu" class="fixed inset-0 z-40 lg:hidden transform translate-y-full opacity-0 transition-all duration-300 ease-in-out flex flex-col justify-end pointer-events-none">
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm pointer-events-auto" onclick="document.getElementById('mobile-menu').classList.add('translate-y-full', 'opacity-0')"></div>
        
        <div class="bg-slate-900 border-t border-[#00f0ff]/30 rounded-t-3xl w-full relative z-10 pointer-events-auto pb-24 pt-6 px-6 shadow-[0_-10px_40px_rgba(0,0,0,0.8)]">
            <div class="w-12 h-1.5 bg-slate-700 rounded-full mx-auto mb-6"></div>

            <h3 class="text-xs font-black text-slate-500 uppercase tracking-widest mb-4">Navigation</h3>
            <div class="grid grid-cols-2 gap-3 mb-6">
                <a href="index.php?module=shop&page=search" class="bg-slate-800 border border-slate-700 p-4 rounded-2xl flex flex-col items-center justify-center gap-2 text-slate-300 hover:text-[#00f0ff] transition shadow-inner">
                    <i class="fas fa-store text-2xl"></i>
                    <span class="text-[10px] font-bold uppercase tracking-wider">All Assets</span>
                </a>
                <a href="index.php?module=user&page=agent" class="bg-slate-800 border border-slate-700 p-4 rounded-2xl flex flex-col items-center justify-center gap-2 text-yellow-500 hover:text-yellow-400 transition shadow-inner">
                    <i class="fas fa-crown text-2xl"></i>
                    <span class="text-[10px] font-bold uppercase tracking-wider">Reseller Hub</span>
                </a>
            </div>

            <?php if(isset($_SESSION['user_id'])): ?>
                <h3 class="text-xs font-black text-slate-500 uppercase tracking-widest mb-4">Command Center</h3>
                <div class="space-y-2">
                    <a href="index.php?module=user&page=dashboard" class="flex items-center gap-4 p-3 bg-slate-800/50 rounded-xl border border-slate-700 text-slate-300 hover:text-white transition">
                        <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center text-blue-400"><i class="fas fa-chart-pie"></i></div>
                        <span class="text-sm font-bold tracking-wide">Dashboard</span>
                    </a>
                    <a href="index.php?module=user&page=wallet" class="flex items-center gap-4 p-3 bg-slate-800/50 rounded-xl border border-slate-700 text-slate-300 hover:text-white transition">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 flex items-center justify-center text-green-400"><i class="fas fa-wallet"></i></div>
                        <span class="text-sm font-bold tracking-wide">Digital Wallet</span>
                    </a>
                    <a href="index.php?module=user&page=wishlist" class="flex items-center gap-4 p-3 bg-slate-800/50 rounded-xl border border-slate-700 text-slate-300 hover:text-white transition">
                        <div class="w-8 h-8 rounded-lg bg-rose-500/10 flex items-center justify-center text-rose-400"><i class="fas fa-heart"></i></div>
                        <span class="text-sm font-bold tracking-wide">Wishlist</span>
                    </a>
                    <a href="index.php?module=user&page=profile" class="flex items-center gap-4 p-3 bg-slate-800/50 rounded-xl border border-slate-700 text-slate-300 hover:text-white transition">
                        <div class="w-8 h-8 rounded-lg bg-slate-700 flex items-center justify-center text-slate-400"><i class="fas fa-cog"></i></div>
                        <span class="text-sm font-bold tracking-wide">Settings</span>
                    </a>
                </div>
                <div class="mt-6">
                    <a href="index.php?module=auth&page=logout" class="w-full flex items-center justify-center gap-2 p-3 bg-red-500/10 text-red-400 font-bold rounded-xl border border-red-500/20">
                        <i class="fas fa-power-off"></i> Terminate Session
                    </a>
                </div>
            <?php else: ?>
                <div class="space-y-3 mt-4">
                    <div class="enable-push-wrapper">
                        <button class="enable-push-btn block w-full text-center p-3 bg-slate-800 border border-slate-600 text-[#00f0ff] font-black uppercase tracking-widest rounded-xl shadow-lg">
                            <i class="fas fa-satellite-dish mr-1"></i> Enable Push Alerts
                        </button>
                    </div>
                    <a href="index.php?module=auth&page=login" class="block w-full text-center p-3 bg-slate-800 border border-slate-600 text-white font-bold rounded-xl shadow-lg">Initiate Login</a>
                    <a href="index.php?module=auth&page=register" class="block w-full text-center p-3 bg-gradient-to-r from-blue-600 to-[#00f0ff] text-slate-900 font-black rounded-xl shadow-[0_0_15px_rgba(0,240,255,0.3)]">Deploy New Account</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Global Search Modal -->
    <div id="search-modal" class="fixed inset-0 z-[60] hidden flex items-start justify-center pt-20 px-4">
        <div class="absolute inset-0 bg-slate-950/90 backdrop-blur-md" onclick="document.getElementById('search-modal').classList.add('hidden')"></div>
        
        <div class="w-full max-w-2xl relative z-10 animate-fade-in-down">
            <form action="index.php" method="GET" class="relative bg-slate-900 rounded-2xl shadow-[0_0_50px_rgba(0,240,255,0.15)] border border-[#00f0ff]/50 overflow-hidden flex items-center p-2 focus-within:shadow-[0_0_40px_rgba(0,240,255,0.3)] transition-all">
                <input type="hidden" name="module" value="shop">
                <input type="hidden" name="page" value="search">
                
                <div class="pl-4 pr-2 text-[#00f0ff]"><i class="fas fa-search text-xl"></i></div>
                <input type="text" name="q" placeholder="Query Matrix..." id="global-search-input"
                       class="flex-1 bg-transparent border-none py-3 px-2 text-white text-lg placeholder-slate-500 focus:outline-none font-mono">
                
                <button type="submit" class="bg-blue-600 hover:bg-[#00f0ff] hover:text-slate-900 text-white px-6 py-3 rounded-xl font-bold transition-colors uppercase tracking-widest text-xs hidden sm:block">Execute</button>
            </form>
            <div class="text-center mt-4 text-xs text-slate-500 font-medium tracking-widest uppercase">
                Press <kbd class="bg-slate-800 border border-slate-700 px-2 py-1 rounded text-slate-300 font-mono">ESC</kbd> to abort
            </div>
        </div>
    </div>

    <!-- Main Content Wrapper -->
    <main class="flex-grow relative z-0">
        <!-- Abstract Background Effects for whole site -->
        <div class="absolute top-0 inset-x-0 h-[500px] overflow-hidden -z-10 pointer-events-none">
            <div class="absolute -top-40 -right-40 w-96 h-96 bg-blue-600/20 rounded-full blur-3xl opacity-50"></div>
            <div class="absolute top-20 -left-40 w-96 h-96 bg-[#00f0ff]/10 rounded-full blur-3xl opacity-30"></div>
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

        // ⚡️ SILENT PUSH SUBSCRIPTION SYNC (Auto-Healing Matrix)
        <?php if(isset($_SESSION['user_id'])): ?>
        window.addEventListener('load', async () => {
            if ('serviceWorker' in navigator && 'Notification' in window && Notification.permission === 'granted') {
                try {
                    if (typeof window.registerServiceWorker === 'function') {
                        console.log('[Matrix] Initiating Background Uplink Sync...');
                        await window.registerServiceWorker(false);
                    }
                } catch(err) { console.error('Matrix Push Sync Error:', err); }
            }
        });
        <?php endif; ?>
    </script>