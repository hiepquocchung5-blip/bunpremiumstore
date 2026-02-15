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
    if ($page) return ($c_mod == $module && $c_page == $page) ? 'text-blue-400 font-bold' : 'text-gray-400 hover:text-white';
    return ($c_mod == $module) ? 'text-blue-400 font-bold' : 'text-gray-400 hover:text-white';
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="description" content="DigitalMarketplaceMM - The #1 Premium Digital Store in Myanmar. Buy Game Keys, Software, and Subscriptions instantly via KBZPay/Wave.">
    <meta name="keywords" content="digital store, myanmar game shop, steam wallet mm, gift cards, premium accounts">
    <meta name="theme-color" content="#111827">
    
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
    <link href="https://fonts.googleapis.com/css2?family=Padauk:wght@400;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">

    <!-- Inline Critical CSS -->
    <style>
        .glass { background: rgba(17, 24, 39, 0.95); backdrop-filter: blur(16px); border-bottom: 1px solid rgba(255,255,255,0.05); }
        body { background-color: #0f172a; color: #f3f4f6; font-family: 'Inter', sans-serif; }
        
        /* Google Translate Hiding */
        .goog-te-banner-frame { display: none !important; }
        body { top: 0 !important; }
        .goog-te-gadget-simple { background-color: #1f2937 !important; border: 1px solid #374151 !important; padding: 4px 8px !important; border-radius: 8px !important; }
        .goog-te-gadget-simple span { color: #e5e7eb !important; font-weight: 600 !important; }
        .goog-te-gadget-icon { display: none !important; }
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
<body class="flex flex-col min-h-screen bg-gray-900 text-gray-100 antialiased selection:bg-blue-500 selection:text-white">

    <!-- Navbar -->
    <nav class="glass sticky top-0 z-50 transition-all duration-300 shadow-lg shadow-black/20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                
                <!-- 1. Logo Area -->
                <a href="index.php" class="flex items-center gap-3 group relative z-10">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg group-hover:shadow-blue-500/40 transition duration-300 border border-white/10">
                        <i class="fas fa-shopping-bag text-white text-lg"></i>
                    </div>
                    <div class="flex flex-col">
                        <span class="font-bold text-xl leading-none tracking-tight text-white group-hover:text-blue-400 transition">Digital<span class="text-blue-500">MM</span></span>
                        <span class="text-[10px] text-gray-400 uppercase tracking-[0.2em] font-bold mt-1">Marketplace</span>
                    </div>
                </a>

                <!-- 2. Desktop Search -->
                <div class="hidden lg:flex flex-1 items-center justify-center px-12">
                    <div class="relative w-full max-w-lg group">
                        <form action="index.php" method="GET">
                            <input type="hidden" name="module" value="shop">
                            <input type="hidden" name="page" value="search">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-500 group-focus-within:text-blue-400 transition"></i>
                            </div>
                            <input type="text" name="q" placeholder="Search games, software, gifts..." 
                                   class="block w-full pl-11 pr-4 py-2.5 bg-gray-800/50 border border-gray-700 rounded-xl leading-5 text-gray-200 placeholder-gray-500 focus:outline-none focus:bg-gray-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 sm:text-sm transition-all duration-300 shadow-inner">
                            <div class="absolute inset-y-0 right-0 pr-2 flex items-center">
                                <kbd class="hidden sm:inline-block border border-gray-600 rounded px-2 text-[10px] font-mono text-gray-500">Ctrl K</kbd>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 3. Right Toolbar -->
                <div class="hidden lg:flex items-center gap-4">
                    
                    <!-- Utilities (Lang + Currency) -->
                    <div class="flex items-center bg-gray-800/80 rounded-xl p-1 border border-gray-700/50 backdrop-blur-sm shadow-sm">
                        <!-- GTranslate -->
                        <div id="google_translate_element" class="mr-2"></div>
                        <div class="h-5 w-px bg-gray-700 mx-1"></div>
                        <!-- Currency -->
                        <a href="?set_curr=MMK" class="px-3 py-1.5 text-xs font-bold rounded-lg transition-all <?php echo (isset($_SESSION['currency']) && $_SESSION['currency']=='MMK') ? 'bg-gradient-to-r from-green-600 to-green-500 text-white shadow-md' : 'text-gray-400 hover:text-white hover:bg-gray-700'; ?>">Ks</a>
                        <a href="?set_curr=USD" class="px-3 py-1.5 text-xs font-bold rounded-lg transition-all <?php echo (isset($_SESSION['currency']) && $_SESSION['currency']=='USD') ? 'bg-gradient-to-r from-green-600 to-green-500 text-white shadow-md' : 'text-gray-400 hover:text-white hover:bg-gray-700'; ?>">$</a>
                    </div>

                    <!-- User Actions -->
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <!-- Notifications -->
                        <div class="relative cursor-pointer text-gray-400 hover:text-white transition group">
                            <div class="w-10 h-10 rounded-xl hover:bg-gray-800 flex items-center justify-center transition">
                                <i class="far fa-bell text-lg"></i>
                            </div>
                            <span class="absolute top-2 right-2 w-2.5 h-2.5 bg-red-500 rounded-full animate-pulse border-2 border-gray-900 hidden"></span>
                            
                            <!-- Dropdown -->
                            <div class="absolute right-0 top-12 w-80 bg-gray-800 rounded-xl shadow-2xl py-2 border border-gray-700 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 transform origin-top-right z-50">
                                <div class="px-4 py-2 border-b border-gray-700 flex justify-between items-center">
                                    <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider">Notifications</h4>
                                    <span class="text-[10px] text-blue-400 cursor-pointer hover:underline" id="mark-read">Mark all read</span>
                                </div>
                                <div class="max-h-64 overflow-y-auto custom-scrollbar">
                                    <!-- Populated by JS -->
                                    <div class="text-xs text-center py-6 text-gray-500">No new notifications</div>
                                </div>
                            </div>
                        </div>

                        <!-- Profile Dropdown -->
                        <div class="relative group">
                            <button class="flex items-center gap-3 focus:outline-none py-2 pl-2">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-gray-700 to-gray-600 p-0.5 shadow-lg border border-gray-600 group-hover:border-blue-500 transition">
                                    <div class="w-full h-full rounded-full bg-gray-800 flex items-center justify-center text-white font-bold text-sm">
                                        <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
                                    </div>
                                </div>
                            </button>
                            
                            <div class="absolute right-0 top-14 w-60 bg-gray-800 rounded-xl shadow-2xl py-2 border border-gray-700 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 transform origin-top-right z-50">
                                <div class="px-5 py-4 border-b border-gray-700 mb-2 bg-gray-800/50">
                                    <p class="text-sm text-white font-bold truncate"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></p>
                                    <p class="text-xs text-gray-400 truncate"><?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?></p>
                                </div>
                                
                                <div class="px-2 space-y-1">
                                    <a href="index.php?module=user&page=dashboard" class="block px-3 py-2 rounded-lg text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition flex items-center gap-3">
                                        <i class="fas fa-chart-pie w-5 text-center text-blue-400"></i> Dashboard
                                    </a>
                                    <a href="index.php?module=user&page=orders" class="block px-3 py-2 rounded-lg text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition flex items-center gap-3">
                                        <i class="fas fa-box-open w-5 text-center text-green-400"></i> My Orders
                                    </a>
                                    <a href="index.php?module=user&page=profile" class="block px-3 py-2 rounded-lg text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition flex items-center gap-3">
                                        <i class="fas fa-user-cog w-5 text-center text-purple-400"></i> Settings
                                    </a>
                                    
                                    <!-- Push Permission Toggle -->
                                    <button id="enable-push" class="w-full text-left px-3 py-2 rounded-lg text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition flex items-center gap-3">
                                        <i class="fas fa-bell w-5 text-center text-yellow-400"></i> Enable Alerts
                                    </button>
                                </div>

                                <div class="border-t border-gray-700 mt-2 pt-2 px-2">
                                    <a href="index.php?module=auth&page=logout" class="block px-3 py-2 rounded-lg text-sm text-red-400 hover:bg-red-500/10 hover:text-red-300 transition flex items-center gap-3">
                                        <i class="fas fa-sign-out-alt w-5 text-center"></i> Sign Out
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="flex items-center gap-3">
                            <a href="index.php?module=auth&page=login" class="text-gray-300 hover:text-white font-medium text-sm transition px-4 py-2 rounded-lg hover:bg-gray-800">Login</a>
                            <a href="index.php?module=auth&page=register" class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white px-5 py-2.5 rounded-xl font-bold text-sm shadow-lg shadow-blue-900/30 transition transform hover:-translate-y-0.5">Get Started</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Mobile Menu Button -->
                <div class="lg:hidden flex items-center">
                    <button onclick="document.getElementById('mobile-menu').classList.toggle('hidden')" class="text-gray-300 hover:text-white p-2 focus:outline-none rounded-lg hover:bg-gray-800 transition">
                        <i class="fas fa-bars text-2xl"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu Overlay -->
        <div id="mobile-menu" class="hidden lg:hidden bg-gray-900/95 backdrop-blur-xl border-b border-gray-700 shadow-2xl absolute w-full z-40 transition-all">
            <div class="px-4 pt-4 pb-6 space-y-4">
                <!-- Mobile Search -->
                <form action="index.php" method="GET" class="relative">
                    <input type="hidden" name="module" value="shop">
                    <input type="hidden" name="page" value="search">
                    <i class="fas fa-search absolute left-3 top-3 text-gray-500"></i>
                    <input type="text" name="q" placeholder="Search products..." class="w-full bg-gray-800 border border-gray-700 rounded-xl py-2.5 pl-10 pr-4 text-white placeholder-gray-500 focus:border-blue-500 outline-none shadow-inner">
                </form>

                <div class="space-y-1">
                    <a href="index.php" class="block px-4 py-3 rounded-xl text-base font-medium text-white bg-blue-600/10 border border-blue-500/20">Home</a>
                    <a href="index.php?module=shop&page=search" class="block px-4 py-3 rounded-xl text-base font-medium text-gray-300 hover:text-white hover:bg-gray-800">Browse Store</a>
                    <a href="index.php?module=info&page=support" class="block px-4 py-3 rounded-xl text-base font-medium text-gray-300 hover:text-white hover:bg-gray-800">Support</a>
                </div>

                <?php if(isset($_SESSION['user_id'])): ?>
                    <div class="border-t border-gray-700 pt-4 pb-2">
                        <div class="flex items-center px-4 mb-4">
                            <div class="h-10 w-10 rounded-full bg-blue-600 flex items-center justify-center text-white font-bold text-lg">
                                <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
                            </div>
                            <div class="ml-3">
                                <div class="text-base font-medium text-white"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                                <div class="text-xs text-gray-400"><?php echo htmlspecialchars($_SESSION['user_email']); ?></div>
                            </div>
                        </div>
                        <div class="space-y-1 px-2">
                            <a href="index.php?module=user&page=dashboard" class="block px-3 py-2 rounded-lg text-sm font-medium text-gray-300 hover:bg-gray-800">Dashboard</a>
                            <a href="index.php?module=user&page=orders" class="block px-3 py-2 rounded-lg text-sm font-medium text-gray-300 hover:bg-gray-800">My Orders</a>
                            <a href="index.php?module=auth&page=logout" class="block px-3 py-2 rounded-lg text-sm font-medium text-red-400 hover:bg-red-500/10">Sign out</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-2 gap-4 border-t border-gray-700 pt-4 px-2">
                        <a href="index.php?module=auth&page=login" class="text-center px-4 py-2.5 border border-gray-600 rounded-xl text-gray-300 font-medium">Login</a>
                        <a href="index.php?module=auth&page=register" class="text-center px-4 py-2.5 bg-blue-600 rounded-xl text-white font-bold">Sign Up</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Content Wrapper -->
    <main class="flex-grow container mx-auto px-4 py-8 relative z-0">