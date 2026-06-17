<?php
// includes/header.php
// PRODUCTION v5.4 - Dynamic Push State Awareness & External API Subdomain Routing

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
    if ($page) return ($c_mod == $module && $c_page == $page) ? 'text-blue-400 font-bold' : 'text-slate-400 hover:text-white transition-colors';
    return ($c_mod == $module) ? 'text-blue-400 font-bold' : 'text-slate-400 hover:text-white transition-colors';
}

// 3. Detect Current Language
$curr_lang = 'en';
if(isset($_COOKIE['googtrans'])) {
    if(strpos($_COOKIE['googtrans'], '/my') !== false) {
        $curr_lang = 'my';
    }
}
$lang_text = $curr_lang == 'my' ? 'MY' : 'EN';

// 4. Current Currency
$curr_currency = $_SESSION['currency'] ?? 'MMK';
$curr_symbol = $curr_currency == 'USD' ? '$' : 'Ks';

// ⚡️ NEW: PERFECT RESUME LOGIC (Track last visited page)
$current_query = $_SERVER['QUERY_STRING'] ?? '';
$is_auth_page = (isset($_GET['module']) && $_GET['module'] === 'auth');
$is_ajax = (isset($_GET['ajax']) && $_GET['ajax'] == 1);

if (!$is_auth_page && !$is_ajax) {
    $_SESSION['resume_url'] = 'index.php' . ($current_query ? '?' . $current_query : '');
}

// ⚡️ GLOBAL CHAT NOTIFICATION LOGIC
$has_unread_chat = false;
if (isset($_SESSION['user_id'])) {
    global $pdo;
    try {
        $stmt_unread = $pdo->prepare("
            SELECT id FROM orders o 
            WHERE user_id = ? 
            AND (
                SELECT sender_type FROM order_messages 
                WHERE order_id = o.id 
                ORDER BY id DESC LIMIT 1
            ) IN ('admin', 'admin_ai')
            LIMIT 1
        ");
        $stmt_unread->execute([$_SESSION['user_id']]);
        $has_unread_chat = $stmt_unread->rowCount() > 0;
    } catch (Exception $e) {}
}

// 5. Database State Check: Is User Subscribed?
$is_push_subscribed = false;
if (isset($_SESSION['user_id'])) {
    global $pdo;
    try {
        $stmt_push = $pdo->prepare("SELECT id FROM push_subscriptions WHERE user_id = ? LIMIT 1");
        $stmt_push->execute([$_SESSION['user_id']]);
        $is_push_subscribed = $stmt_push->rowCount() > 0;
    } catch (Exception $e) {
        // Fail silently if table doesn't exist yet
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="DigitalMM">
    <meta name="theme-color" content="#0b0f1a">
    
    <title>DigitalMM | Premium Digital Marketplace</title>
    
    <!-- CSS Dependencies -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">

    <!-- Inline Critical CSS -->
    <style>
        .glass { background: rgba(11, 15, 26, 0.8); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        .glass-pill { background: rgba(21, 28, 44, 0.9); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); border: 1px solid rgba(255, 255, 255, 0.1); }
        body { background-color: #0b0f1a; color: #f8fafc; font-family: 'Inter', sans-serif; -webkit-tap-highlight-color: transparent; }
        
        /* Completely Hide Google Translate Default UI */
        .goog-te-banner-frame, .skiptranslate, #google_translate_element { display: none !important; }
        body { top: 0 !important; }

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
            baseUrl: "<?php echo defined('BASE_URL') ? BASE_URL : '/'; ?>",
            // Explicitly routing API endpoints to the API Subdomain (Environment driven)
            pushApiUrl: "<?php echo $_ENV['PUSH_API_URL'] ?? 'https://api.digitalmarketplacemm.com/push_subscribe.php'; ?>",
            notificationsApiUrl: "<?php echo $_ENV['NOTIF_API_URL'] ?? 'https://api.digitalmarketplacemm.com/notifications.php'; ?>"
        };
    </script>
</head>
<body class="flex flex-col min-h-screen bg-[#0b0f1a] text-slate-100 antialiased selection:bg-blue-500/30 selection:text-blue-200">

    <div id="google_translate_element"></div>

    <!-- Top Navbar -->
    <nav class="glass sticky top-0 z-[100] transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 lg:h-20">
                
                <!-- Logo -->
                <a href="index.php" class="flex items-center gap-3 group relative z-10 shrink-0">
                    <div class="w-10 h-10 lg:w-12 lg:h-12 bg-slate-800 rounded-2xl flex items-center justify-center shadow-lg group-hover:shadow-blue-500/20 transition duration-300 border border-white/5 overflow-hidden">
                        <img src="assets/images/logo.png" alt="Logo" class="w-full h-full object-contain p-1.5 transition-transform duration-500 group-hover:scale-110" onerror="this.outerHTML='<i class=\'fas fa-bolt text-blue-500 text-xl\'></i>'">
                    </div>
                    <div class="flex flex-col">
                        <span class="font-bold text-lg lg:text-xl tracking-tight text-white group-hover:text-blue-400 transition">Digital<span class="text-blue-500">MM</span></span>
                        <span class="text-[9px] text-slate-500 uppercase tracking-widest font-bold">Marketplace</span>
                    </div>
                </a>

                <!-- Desktop Search Bar -->
                <div class="hidden lg:flex flex-1 items-center justify-center px-10">
                    <div class="relative w-full max-w-lg">
                        <button onclick="openSearchModal()" class="w-full flex items-center justify-between bg-slate-900/40 border border-white/5 hover:border-blue-500/30 rounded-2xl py-2.5 px-5 text-sm text-slate-400 transition-all duration-300 group">
                            <span class="flex items-center gap-3">
                                <i class="fas fa-search text-slate-500 group-hover:text-blue-400 transition"></i>
                                Find games, software, gifts...
                            </span>
                            <kbd class="border border-white/10 bg-slate-800 rounded-md px-2 py-0.5 text-[10px] text-slate-500 font-medium">Search</kbd>
                        </button>
                    </div>
                </div>

                <!-- Right Tools -->
                <div class="flex items-center gap-3 lg:gap-6 relative z-10">
                    
                    <div class="hidden lg:flex items-center gap-6">
                        <a href="index.php?module=shop&page=search" class="text-sm font-semibold <?php echo isActive('shop', 'search'); ?>">Store</a>
                        <a href="index.php?module=user&page=agent" class="text-sm font-semibold flex items-center gap-2 <?php echo isActive('user', 'agent'); ?>">
                            <i class="fas fa-crown text-yellow-500 text-xs"></i> Reseller
                        </a>
                    </div>

                    <!-- Localization Dropdown -->
                    <div class="relative group">
                        <button class="flex items-center gap-2.5 bg-slate-900/60 border border-white/5 hover:border-blue-500/30 rounded-xl px-3 py-2 transition-all duration-300">
                            <i class="fas fa-globe text-slate-400 group-hover:text-blue-400"></i>
                            <span class="text-xs font-bold text-white hidden sm:inline"><?php echo $lang_text; ?> <span class="text-slate-600 mx-1">|</span> <span class="text-blue-400"><?php echo $curr_symbol; ?></span></span>
                        </button>
                        
                        <!-- Dropdown -->
                        <div class="absolute right-0 top-full mt-2 w-56 bg-slate-900 border border-white/10 rounded-2xl shadow-2xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50 overflow-hidden">
                            <div class="p-2 space-y-1">
                                <div class="px-3 py-2 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Language</div>
                                <button onclick="changeLanguage('en')" class="w-full text-left px-3 py-2.5 text-sm text-slate-300 hover:bg-slate-800 hover:text-white rounded-xl flex items-center justify-between transition">
                                    <span class="flex items-center gap-3">🇺🇸 English</span>
                                    <?php if($curr_lang=='en') echo '<i class="fas fa-check text-blue-500 text-xs"></i>'; ?>
                                </button>
                                <button onclick="changeLanguage('my')" class="w-full text-left px-3 py-2.5 text-sm text-slate-300 hover:bg-slate-800 hover:text-white rounded-xl flex items-center justify-between transition">
                                    <span class="flex items-center gap-3">🇲🇲 Myanmar</span>
                                    <?php if($curr_lang=='my') echo '<i class="fas fa-check text-blue-500 text-xs"></i>'; ?>
                                </button>
                                
                                <div class="h-px bg-white/5 my-2"></div>
                                
                                <div class="px-3 py-2 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Currency</div>
                                <a href="?set_curr=MMK" class="flex items-center justify-between px-3 py-2.5 text-sm text-slate-300 hover:bg-slate-800 hover:text-white rounded-xl transition">
                                    <span>MMK (Kyat)</span>
                                    <?php if($curr_currency=='MMK') echo '<i class="fas fa-check text-blue-500 text-xs"></i>'; ?>
                                </a>
                                <a href="?set_curr=USD" class="flex items-center justify-between px-3 py-2.5 text-sm text-slate-300 hover:bg-slate-800 hover:text-white rounded-xl transition">
                                    <span>USD ($)</span>
                                    <?php if($curr_currency=='USD') echo '<i class="fas fa-check text-blue-500 text-xs"></i>'; ?>
                                </a>
                            </div>
                        </div>
                    </div>

                    <?php if(isset($_SESSION['user_id'])): ?>
                        
                        <!-- Profile -->
                        <div class="relative group">
                            <button class="flex items-center gap-2 focus:outline-none">
                                <div class="w-9 h-9 lg:w-10 lg:h-10 rounded-full bg-slate-800 border-2 border-white/5 group-hover:border-blue-500/50 transition-all flex items-center justify-center text-sm font-bold text-white shadow-md">
                                    <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
                                </div>
                            </button>
                            
                            <div class="absolute right-0 top-full mt-2 w-64 bg-slate-900 border border-white/10 rounded-2xl shadow-2xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50 overflow-hidden">
                                <div class="p-4 border-b border-white/5 bg-white/5">
                                    <p class="text-sm font-bold text-white"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></p>
                                    <p class="text-xs text-slate-400 mt-0.5"><?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?></p>
                                </div>
                                <div class="p-2 space-y-1">
                                    <a href="index.php?module=user&page=dashboard" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm text-slate-300 hover:bg-slate-800 hover:text-white transition">
                                        <i class="fas fa-columns w-5 text-slate-500"></i> Dashboard
                                    </a>
                                    <a href="index.php?module=user&page=orders" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm text-slate-300 hover:bg-slate-800 hover:text-white transition">
                                        <i class="fas fa-box w-5 text-slate-500"></i> My Orders
                                    </a>
                                    <a href="index.php?module=user&page=wishlist" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm text-slate-300 hover:bg-slate-800 hover:text-white transition">
                                        <i class="fas fa-heart w-5 text-slate-500"></i> Wishlist
                                    </a>
                                    <div class="h-px bg-white/5 my-1"></div>
                                    <a href="index.php?module=auth&page=logout" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm text-rose-400 hover:bg-rose-500/10 transition font-bold">
                                        <i class="fas fa-power-off w-5"></i> Logout
                                    </a>
                                </div>
                            </div>
                        </div>

                    <?php else: ?>
                        <div class="flex items-center gap-3">
                            <a href="index.php?module=auth&page=login" class="hidden sm:block text-sm font-semibold text-slate-300 hover:text-white transition px-4 py-2">Login</a>
                            <a href="index.php?module=auth&page=register" class="bg-blue-600 hover:bg-blue-500 text-white px-5 py-2.5 rounded-xl font-bold text-sm transition-all shadow-lg shadow-blue-500/20">Get Started</a>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </nav>

    <!-- Bottom Nav (Mobile Only) -->
    <div class="fixed bottom-6 left-6 right-6 z-[90] lg:hidden">
        <div class="glass-pill rounded-3xl px-8 py-3.5 flex justify-between items-center shadow-2xl relative">
            <a href="index.php" class="flex flex-col items-center gap-1 <?php echo isActive('home'); ?>">
                <i class="fas fa-home text-lg"></i>
                <span class="text-[9px] font-bold uppercase tracking-wider">Home</span>
            </a>
            <button onclick="openSearchModal()" class="flex flex-col items-center gap-1 text-slate-400">
                <i class="fas fa-search text-lg"></i>
                <span class="text-[9px] font-bold uppercase tracking-wider">Search</span>
            </button>
            <div class="relative -top-7">
                <a href="index.php?module=shop&page=category" class="w-14 h-14 bg-blue-600 text-white rounded-full flex items-center justify-center shadow-xl border-4 border-[#0b0f1a] transform active:scale-95 transition">
                    <i class="fas fa-layer-group text-xl"></i>
                </a>
            </div>
            <a href="index.php?module=user&page=orders" class="flex flex-col items-center gap-1 <?php echo isActive('user', 'orders'); ?>">
                <i class="fas fa-box text-lg"></i>
                <span class="text-[9px] font-bold uppercase tracking-wider">Orders</span>
            </a>
            <button onclick="document.getElementById('mobile-menu').classList.toggle('translate-y-full'); document.getElementById('mobile-menu').classList.toggle('opacity-0');" class="flex flex-col items-center gap-1 text-slate-400">
                <i class="fas fa-bars text-lg"></i>
                <span class="text-[9px] font-bold uppercase tracking-wider">Menu</span>
            </button>
        </div>
    </div>

    <!-- Mobile Menu Overlay -->
    <div id="mobile-menu" class="fixed inset-0 z-[100] lg:hidden transform translate-y-full opacity-0 transition-all duration-300 ease-in-out flex flex-col justify-end pointer-events-none">
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm pointer-events-auto" onclick="document.getElementById('mobile-menu').classList.add('translate-y-full', 'opacity-0')"></div>
        
        <div class="bg-slate-900 border-t border-white/10 rounded-t-[2.5rem] w-full relative z-10 pointer-events-auto pb-24 pt-8 px-6 shadow-2xl max-h-[85vh] overflow-y-auto">
            <div class="w-12 h-1.5 bg-slate-700 rounded-full mx-auto mb-8"></div>

            <div class="grid grid-cols-2 gap-4 mb-8">
                <a href="index.php?module=shop&page=search" class="bg-slate-800/50 border border-white/5 p-5 rounded-2xl flex flex-col items-center gap-3 text-slate-300 hover:text-blue-400 transition">
                    <i class="fas fa-store text-2xl"></i>
                    <span class="text-[10px] font-bold uppercase tracking-widest">Browse Store</span>
                </a>
                <a href="index.php?module=user&page=agent" class="bg-slate-800/50 border border-white/5 p-5 rounded-2xl flex flex-col items-center gap-3 text-yellow-500 hover:text-yellow-400 transition">
                    <i class="fas fa-crown text-2xl"></i>
                    <span class="text-[10px] font-bold uppercase tracking-widest">Reseller</span>
                </a>
            </div>

            <?php if(isset($_SESSION['user_id'])): ?>
                <div class="space-y-3">
                    <a href="index.php?module=user&page=dashboard" class="flex items-center gap-4 p-4 bg-slate-800/30 rounded-2xl border border-white/5 text-slate-300">
                        <i class="fas fa-columns text-blue-400"></i>
                        <span class="text-sm font-semibold">Dashboard</span>
                    </a>
                    <a href="index.php?module=user&page=orders" class="flex items-center gap-4 p-4 bg-slate-800/30 rounded-2xl border border-white/5 text-slate-300">
                        <i class="fas fa-box text-emerald-400"></i>
                        <span class="text-sm font-semibold">My Orders</span>
                    </a>
                    <a href="index.php?module=user&page=wishlist" class="flex items-center gap-4 p-4 bg-slate-800/30 rounded-2xl border border-white/5 text-slate-300">
                        <i class="fas fa-heart text-rose-400"></i>
                        <span class="text-sm font-semibold">Wishlist</span>
                    </a>
                    <a href="index.php?module=auth&page=logout" class="flex items-center justify-center gap-2 p-4 bg-rose-500/10 text-rose-400 font-bold rounded-2xl border border-rose-500/20 mt-4">
                        <i class="fas fa-power-off"></i> Logout
                    </a>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <a href="index.php?module=auth&page=login" class="block w-full text-center p-4 bg-slate-800 border border-white/10 text-white font-bold rounded-2xl">Login</a>
                    <a href="index.php?module=auth&page=register" class="block w-full text-center p-4 bg-blue-600 text-white font-bold rounded-2xl shadow-lg shadow-blue-500/20">Sign Up</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Search Modal -->
    <div id="search-modal" class="fixed inset-0 z-[120] hidden flex items-start justify-center md:pt-20 px-0 md:px-4">
        <div class="absolute inset-0 bg-slate-950/90 backdrop-blur-xl" onclick="closeSearchModal()"></div>
        
        <div class="w-full max-w-2xl relative z-10 h-full md:h-auto flex flex-col px-4 pt-10 md:pt-0">
            <form action="index.php" method="GET" class="relative bg-slate-900 md:rounded-3xl border border-white/10 overflow-hidden flex items-center p-2 focus-within:border-blue-500/50 transition-all">
                <input type="hidden" name="module" value="shop">
                <input type="hidden" name="page" value="search">
                
                <div class="pl-4 pr-2 text-slate-500"><i class="fas fa-search text-lg"></i></div>
                <input type="text" name="q" placeholder="Search games, gifts, tools..." id="global-search-input"
                       class="flex-1 bg-transparent border-none py-4 px-2 text-white text-lg placeholder-slate-500 focus:outline-none font-medium">
                
                <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white px-6 py-3 rounded-2xl font-bold transition-colors">Search</button>
            </form>

            <div class="mt-8 md:mt-6 p-4">
                <h4 class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.2em] mb-4">Quick Links</h4>
                <div class="flex flex-wrap gap-2">
                    <a href="index.php?module=shop&page=search&q=Netflix" class="px-5 py-2.5 bg-slate-800/50 border border-white/5 rounded-full text-xs text-slate-300 hover:border-blue-500/50 hover:text-blue-400 transition">Netflix</a>
                    <a href="index.php?module=shop&page=search&q=Steam" class="px-5 py-2.5 bg-slate-800/50 border border-white/5 rounded-full text-xs text-slate-300 hover:border-blue-500/50 hover:text-blue-400 transition">Steam</a>
                    <a href="index.php?module=shop&page=search&q=ChatGPT" class="px-5 py-2.5 bg-slate-800/50 border border-white/5 rounded-full text-xs text-slate-300 hover:border-blue-500/50 hover:text-blue-400 transition">ChatGPT</a>
                    <a href="index.php?module=shop&page=search&q=Spotify" class="px-5 py-2.5 bg-slate-800/50 border border-white/5 rounded-full text-xs text-slate-300 hover:border-blue-500/50 hover:text-blue-400 transition">Spotify</a>
                </div>
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
        // Global Search Modal Controls
        function openSearchModal() {
            const modal = document.getElementById('search-modal');
            modal.classList.remove('hidden');
            // Prevent body scroll on mobile
            if(window.innerWidth < 768) {
                document.body.style.overflow = 'hidden';
                document.body.style.height = '100svh';
            }
            setTimeout(() => {
                document.getElementById('global-search-input').focus();
            }, 100);
        }

        function closeSearchModal() {
            document.getElementById('search-modal').classList.add('hidden');
            document.body.style.overflow = '';
            document.body.style.height = '';
        }

        // Keyboard shortcuts for search modal
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                openSearchModal();
            }
            if (e.key === 'Escape') {
                closeSearchModal();
            }
        });

        // Update all search buttons to use openSearchModal
        document.addEventListener('DOMContentLoaded', () => {
            const searchTriggers = document.querySelectorAll('[onclick*="search-modal"]');
            searchTriggers.forEach(trigger => {
                trigger.setAttribute('onclick', 'openSearchModal()');
            });
        });

        // ⚡️ INLINE DEBUG & FAILSAFE FOR PUSH BUTTON (Overrides app.js if needed)
        document.addEventListener('DOMContentLoaded', () => {
            // Premium iOS Helper Injector
            function showIosHelper() {
                const helper = document.createElement('div');
                helper.id = 'ios-helper-modal';
                helper.className = 'fixed inset-0 z-[200] flex items-center justify-center p-4 animate-fade-in';
                helper.innerHTML = `
                    <div class="absolute inset-0 bg-slate-950/80 backdrop-blur-md" onclick="this.parentElement.remove()"></div>
                    <div class="relative bg-slate-900 border border-[#00f0ff]/30 w-full max-w-sm rounded-3xl overflow-hidden shadow-[0_20px_50px_rgba(0,0,0,0.8)] animate-fade-in-up">
                        <div class="p-6 text-center">
                            <div class="w-20 h-20 bg-[#00f0ff]/10 rounded-full flex items-center justify-center mx-auto mb-6 border border-[#00f0ff]/20 shadow-[0_0_20px_rgba(0,240,255,0.1)]">
                                <i class="fab fa-apple text-4xl text-white"></i>
                            </div>
                            <h3 class="text-xl font-black text-white mb-2 uppercase tracking-tight">Enable Notifications</h3>
                            <p class="text-xs text-slate-400 leading-relaxed mb-6">Follow these simple steps to enable order updates on your device.</p>
                            
                            <div class="space-y-4 text-left">
                                <div class="flex gap-4 p-3 bg-slate-800/50 rounded-2xl border border-slate-700">
                                    <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center shrink-0 font-black text-xs text-white">1</div>
                                    <p class="text-[11px] text-slate-300 font-medium">Tap the <i class="fas fa-share-square text-[#00f0ff]"></i> <b>Share</b> icon in Safari.</p>
                                </div>
                                <div class="flex gap-4 p-3 bg-slate-800/50 rounded-2xl border border-slate-700">
                                    <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center shrink-0 font-black text-xs text-white">2</div>
                                    <p class="text-[11px] text-slate-300 font-medium">Select <i class="fas fa-plus-square text-[#00f0ff]"></i> <b>Add to Home Screen</b>.</p>
                                </div>
                                <div class="flex gap-4 p-3 bg-slate-800/50 rounded-2xl border border-slate-700">
                                    <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center shrink-0 font-black text-xs text-white">3</div>
                                    <p class="text-[11px] text-slate-300 font-medium">Open the app from your home screen and enable alerts again.</p>
                                </div>
                            </div>
                        </div>
                        <button onclick="this.parentElement.parentElement.remove()" class="w-full py-4 bg-[#00f0ff] hover:bg-blue-400 text-slate-900 font-black uppercase tracking-widest text-xs transition">Understood</button>
                    </div>
                `;
                document.body.appendChild(helper);
            }

            document.querySelectorAll('.enable-push-btn').forEach(btn => {
                btn.onclick = (e) => {
                    e.preventDefault();
                    e.stopPropagation();

                    // Detect iOS
                    const isIos = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
                    const isStandalone = window.navigator.standalone || window.matchMedia('(display-mode: standalone)').matches;

                    if (isIos && !isStandalone) {
                    showIosHelper();
                    return;
                }
                
                const icon = btn.querySelector('i');
                if (icon) icon.className = "fas fa-spinner fa-spin";
                
                if (typeof window.registerServiceWorker === 'function') {
                    // Pass 'true' to ensure the local welcome push triggers
                    window.registerServiceWorker(true).then(() => {
                        // Visually remove the prompt block upon success with a smooth transition
                        const wrapper = btn.closest('.enable-push-wrapper');
                        
                        // Visual Success State
                        btn.innerHTML = `<i class="fas fa-check"></i> Enabled`;
                        btn.classList.replace('bg-[#00f0ff]', 'bg-green-500');
                        btn.classList.replace('text-[#00f0ff]', 'text-green-400');
                        
                        setTimeout(() => {
                            if (wrapper && wrapper.classList.contains('group/prompt')) {
                                // If inside dropdown, replace with badge
                                wrapper.innerHTML = `
                                <div class="w-full flex justify-center items-center py-2">
                                    <span class="text-[9px] text-green-400 font-black uppercase tracking-widest flex items-center gap-2">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse shadow-[0_0_5px_#22c55e]"></span> Alerts Active
                                    </span>
                                </div>`;
                            } else if (wrapper) {
                                wrapper.style.opacity = '0';
                                setTimeout(() => wrapper.remove(), 300);
                            } else {
                                btn.remove();
                            }
                        }, 800);

                    }).catch(err => {
                        console.error('Manual setup Error:', err);
                        if (icon) icon.className = "fas fa-bell-slash text-red-500";
                        
                        if (isIos) {
                            showIosHelper();
                        } else {
                            alert("Setup failed. Please allow notifications in your browser settings.");
                        }
                    });
                } else {
                    console.error('Support script not loaded.');
                    if (icon) icon.className = "fas fa-exclamation-triangle text-red-500";
                }
                };
            });
        });

        // ⚡️ SILENT PUSH SUBSCRIPTION SYNC (Auto-Healing Matrix)
        <?php if(isset($_SESSION['user_id'])): ?>
        window.addEventListener('load', async () => {
            if ('serviceWorker' in navigator && 'Notification' in window && Notification.permission === 'granted') {
                try {
                    if (typeof window.registerServiceWorker === 'function') {
                        await window.registerServiceWorker(false);

                        // Clean up UI if sync resolves silently
                        document.querySelectorAll('.enable-push-wrapper').forEach(el => {
                            if (el.classList.contains('group/prompt')) {
                                el.innerHTML = `
                                    <div class="w-full flex justify-center items-center py-2">
                                        <span class="text-[9px] text-green-400 font-black uppercase tracking-widest flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse shadow-[0_0_5px_#22c55e]"></span> Alerts Active
                                        </span>
                                    </div>`;
                            } else {
                                el.remove();
                            }
                        });
                    }
                } catch(err) { console.error('Matrix Push Sync Error:', err); }
            }
        });
        <?php endif; ?>
    </script>