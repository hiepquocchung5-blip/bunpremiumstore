<?php
// includes/header.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper for active class (optional usage in links)
function isActive($module, $page = null) {
    $current_mod = $_GET['module'] ?? 'home';
    $current_page = $_GET['page'] ?? 'index';
    if ($page) {
        return ($current_mod == $module && $current_page == $page) ? 'text-blue-400' : 'text-gray-300 hover:text-white';
    }
    return ($current_mod == $module) ? 'text-blue-400' : 'text-gray-300 hover:text-white';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DigitalMarketplaceMM</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Fonts: Inter & Padauk -->
    <link href="https://fonts.googleapis.com/css2?family=Padauk:wght@400;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS (Assuming style.css exists, inline fallback provided) -->
    <link href="assets/css/style.css" rel="stylesheet">

    <style>
        /* Essential styles if style.css fails to load */
        .glass { background: rgba(17, 24, 39, 0.85); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(255,255,255,0.05); }
        body { background-color: #111827; color: #f3f4f6; font-family: 'Inter', sans-serif; }
        
        /* Google Translate Widget Styling */
        .goog-te-gadget-simple { background-color: #1f2937 !important; border: 1px solid #374151 !important; padding: 4px 8px !important; border-radius: 6px !important; }
        .goog-te-gadget-simple span { color: #d1d5db !important; font-weight: 600 !important; font-family: 'Inter', sans-serif !important; }
        .goog-te-gadget-icon { display: none !important; }
        .goog-te-banner-frame { display: none !important; }
        body { top: 0 !important; }
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
<body class="flex flex-col min-h-screen bg-gray-900 text-gray-100">

    <!-- Navbar -->
    <nav class="glass sticky top-0 z-50 transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                
                <!-- 1. Logo -->
                <a href="index.php" class="flex items-center gap-3 group">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-blue-700 rounded-xl flex items-center justify-center shadow-lg group-hover:shadow-blue-500/50 transition duration-300">
                        <i class="fas fa-shopping-bag text-white text-lg"></i>
                    </div>
                    <div class="flex flex-col">
                        <span class="font-bold text-lg leading-tight tracking-tight text-white group-hover:text-blue-400 transition">Digital<span class="text-blue-500">MM</span></span>
                        <span class="text-[10px] text-gray-400 uppercase tracking-widest font-semibold">Premium Store</span>
                    </div>
                </a>

                <!-- 2. Desktop Navigation & Search -->
                <div class="hidden lg:flex flex-1 items-center justify-center px-8">
                    <!-- Search Bar -->
                    <div class="relative w-full max-w-lg group">
                        <form action="index.php" method="GET">
                            <input type="hidden" name="module" value="shop">
                            <input type="hidden" name="page" value="search">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-500 group-focus-within:text-blue-500 transition"></i>
                            </div>
                            <input type="text" name="q" placeholder="Search for games, software, accounts..." 
                                   class="block w-full pl-11 pr-4 py-2.5 bg-gray-800/50 border border-gray-700 rounded-full leading-5 text-gray-300 placeholder-gray-500 focus:outline-none focus:bg-gray-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 sm:text-sm transition-all duration-300 shadow-inner">
                        </form>
                    </div>
                </div>

                <!-- 3. Right Actions -->
                <div class="hidden lg:flex items-center gap-4">
                    
                    <!-- Tools: Lang & Currency -->
                    <div class="flex items-center bg-gray-800/80 rounded-lg p-1 border border-gray-700/50 backdrop-blur-sm">
                        <!-- GTranslate Container -->
                        <div id="google_translate_element" class="mr-2"></div>
                        
                        <div class="h-4 w-px bg-gray-700 mx-1"></div>

                        <!-- Currency -->
                        <a href="?set_curr=MMK" class="px-2.5 py-1 text-xs font-bold rounded transition-colors <?php echo (isset($_SESSION['currency']) && $_SESSION['currency']=='MMK') ? 'bg-green-600 text-white shadow' : 'text-gray-400 hover:text-white'; ?>">Ks</a>
                        <a href="?set_curr=USD" class="px-2.5 py-1 text-xs font-bold rounded transition-colors <?php echo (isset($_SESSION['currency']) && $_SESSION['currency']=='USD') ? 'bg-green-600 text-white shadow' : 'text-gray-400 hover:text-white'; ?>">$</a>
                    </div>

                    <!-- User Actions -->
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <div class="relative group">
                            <button class="flex items-center gap-3 focus:outline-none py-2">
                                <div class="w-10 h-10 rounded-full bg-gray-800 border border-gray-600 flex items-center justify-center text-blue-400 group-hover:border-blue-500 transition overflow-hidden shadow-lg">
                                    <span class="font-bold text-sm"><?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?></span>
                                </div>
                            </button>
                            
                            <!-- Dropdown Menu -->
                            <div class="absolute right-0 mt-2 w-56 bg-gray-800 rounded-xl shadow-2xl py-2 border border-gray-700 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 transform origin-top-right z-50">
                                <div class="px-4 py-3 border-b border-gray-700 mb-1">
                                    <p class="text-sm text-white font-bold truncate"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></p>
                                    <p class="text-xs text-gray-400 truncate"><?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?></p>
                                </div>
                                <a href="index.php?module=user&page=orders" class="block px-4 py-2.5 text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition flex items-center gap-3">
                                    <i class="fas fa-box-open w-4 text-center"></i> My Orders
                                </a>
                                <a href="index.php?module=user&page=profile" class="block px-4 py-2.5 text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition flex items-center gap-3">
                                    <i class="fas fa-cog w-4 text-center"></i> Settings
                                </a>
                                <a href="index.php?module=user&page=agent" class="block px-4 py-2.5 text-sm text-yellow-400 hover:bg-gray-700 hover:text-yellow-300 transition flex items-center gap-3">
                                    <i class="fas fa-crown w-4 text-center"></i> Agent Hub
                                </a>
                                <div class="border-t border-gray-700 my-1"></div>
                                <a href="index.php?module=auth&page=logout" class="block px-4 py-2.5 text-sm text-red-400 hover:bg-gray-700 hover:text-red-300 transition flex items-center gap-3">
                                    <i class="fas fa-sign-out-alt w-4 text-center"></i> Logout
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="flex items-center gap-3">
                            <a href="index.php?module=auth&page=login" class="text-gray-300 hover:text-white font-medium text-sm transition px-3 py-2 rounded-lg hover:bg-gray-800">Login</a>
                            <a href="index.php?module=auth&page=register" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg font-bold text-sm shadow-lg shadow-blue-900/30 transition transform hover:-translate-y-0.5">Sign Up</a>
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

        <!-- Mobile Menu (Hidden by default) -->
        <div id="mobile-menu" class="hidden lg:hidden bg-gray-800 border-b border-gray-700 shadow-xl absolute w-full z-40 transition-all">
            <div class="px-4 pt-4 pb-6 space-y-4">
                
                <!-- Mobile Search -->
                <form action="index.php" method="GET" class="relative">
                    <input type="hidden" name="module" value="shop">
                    <input type="hidden" name="page" value="search">
                    <i class="fas fa-search absolute left-3 top-3 text-gray-500"></i>
                    <input type="text" name="q" placeholder="Search products..." class="w-full bg-gray-900 border border-gray-600 rounded-lg py-2.5 pl-10 pr-4 text-white placeholder-gray-500 focus:border-blue-500 outline-none">
                </form>

                <!-- Navigation Links -->
                <div class="space-y-1">
                    <a href="index.php" class="block px-3 py-2 rounded-md text-base font-medium text-white bg-gray-900 border-l-4 border-blue-500">Home</a>
                    <a href="index.php?module=shop&page=search" class="block px-3 py-2 rounded-md text-base font-medium text-gray-300 hover:text-white hover:bg-gray-700">Browse Store</a>
                    <a href="index.php?module=info&page=support" class="block px-3 py-2 rounded-md text-base font-medium text-gray-300 hover:text-white hover:bg-gray-700">Support Center</a>
                </div>

                <!-- Tools Row -->
                <div class="border-t border-gray-700 pt-4 flex items-center justify-between">
                    <div class="text-xs text-gray-400">Settings:</div>
                    <div class="flex items-center gap-3">
                        <!-- Only works if not obscured by another element, simpler display for mobile -->
                        <div class="flex items-center bg-gray-900 rounded p-1">
                            <a href="?set_curr=MMK" class="px-3 py-1 text-xs rounded <?php echo (isset($_SESSION['currency']) && $_SESSION['currency']=='MMK')?'bg-green-600 text-white':'text-gray-400'; ?>">Ks</a>
                            <a href="?set_curr=USD" class="px-3 py-1 text-xs rounded <?php echo (isset($_SESSION['currency']) && $_SESSION['currency']=='USD')?'bg-green-600 text-white':'text-gray-400'; ?>">$</a>
                        </div>
                    </div>
                </div>

                <!-- User Section -->
                <?php if(isset($_SESSION['user_id'])): ?>
                    <div class="border-t border-gray-700 pt-4 bg-gray-900/50 -mx-4 px-4 pb-2">
                        <div class="flex items-center mb-3">
                            <div class="flex-shrink-0">
                                <div class="h-10 w-10 rounded-full bg-blue-600 flex items-center justify-center text-white font-bold text-lg">
                                    <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
                                </div>
                            </div>
                            <div class="ml-3">
                                <div class="text-base font-medium leading-none text-white"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                                <div class="text-sm font-medium leading-none text-gray-400 mt-1"><?php echo htmlspecialchars($_SESSION['user_email']); ?></div>
                            </div>
                        </div>
                        <div class="space-y-1">
                            <a href="index.php?module=user&page=orders" class="block px-3 py-2 rounded-md text-base font-medium text-gray-300 hover:text-white hover:bg-gray-700">My Orders</a>
                            <a href="index.php?module=user&page=profile" class="block px-3 py-2 rounded-md text-base font-medium text-gray-300 hover:text-white hover:bg-gray-700">Profile Settings</a>
                            <a href="index.php?module=auth&page=logout" class="block px-3 py-2 rounded-md text-base font-medium text-red-400 hover:text-red-300 hover:bg-gray-700">Sign out</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-2 gap-4 border-t border-gray-700 pt-4">
                        <a href="index.php?module=auth&page=login" class="text-center px-4 py-2 border border-gray-600 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition">Login</a>
                        <a href="index.php?module=auth&page=register" class="text-center px-4 py-2 bg-blue-600 rounded-lg text-white font-bold hover:bg-blue-700 transition">Sign Up</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Content Wrapper -->
    <main class="flex-grow container mx-auto px-4 py-8 relative z-0">