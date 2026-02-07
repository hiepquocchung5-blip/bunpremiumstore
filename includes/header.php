<?php
// includes/header.php

// Ensure session is started (if not already by config)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ScottSub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Padauk:wght@400;700&family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <style>
        .glass { background: rgba(30, 41, 59, 0.8); backdrop-filter: blur(10px); }
        body { 
            background-color: #111827; 
            color: #fff; 
            font-family: 'Inter', sans-serif; /* Google Translate handles font switching automatically */
            transition: background 0.3s; 
        }

        /* --- Google Translate Dark Mode Overrides --- */
        /* Hide top banner */
        .goog-te-banner-frame { display: none !important; }
        body { top: 0 !important; }
        
        /* Style the dropdown container */
        .goog-te-gadget-simple {
            background-color: #1f2937 !important; /* bg-gray-800 */
            border: 1px solid #374151 !important; /* border-gray-700 */
            border-radius: 0.5rem !important;     /* rounded-lg */
            padding: 0.3rem 0.5rem !important;
            display: flex !important;
            align-items: center !important;
            height: 34px !important;
            transition: all 0.2s;
        }
        
        .goog-te-gadget-simple:hover {
            background-color: #374151 !important; /* hover:bg-gray-700 */
        }

        /* Style text inside dropdown */
        .goog-te-gadget-simple span {
            color: #d1d5db !important; /* text-gray-300 */
            font-size: 0.75rem !important; /* text-xs */
            font-weight: 700 !important;
            font-family: 'Inter', sans-serif !important;
        }
        
        /* Hide Google Icon */
        .goog-te-gadget-icon { display: none !important; }
        
        /* Remove margins */
        .goog-te-gadget-simple .goog-te-menu-value { margin: 0 !important; }
        .goog-te-gadget-simple .goog-te-menu-value span { border-left: none !important; }
        
        /* Hide tooltips */
        .goog-tooltip { display: none !important; }
        .goog-tooltip:hover { display: none !important; }
        .goog-text-highlight { background-color: transparent !important; border: none !important; box-shadow: none !important; }
    </style>

    <!-- Google Translate Script -->
    <script type="text/javascript">
        function googleTranslateElementInit() {
            new google.translate.TranslateElement({
                pageLanguage: 'en',
                includedLanguages: 'en,my', // Only English and Myanmar (Burmese)
                layout: google.translate.TranslateElement.InlineLayout.SIMPLE,
                autoDisplay: false
            }, 'google_translate_element');
        }
    </script>
    <script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
</head>
<body class="flex flex-col min-h-screen">
    <nav class="glass border-b border-gray-700 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
            
            <!-- Logo -->
            <a href="index.php" class="text-xl font-bold flex items-center gap-2 mr-4 notranslate">
                <div class="w-8 h-8 bg-blue-600 rounded flex items-center justify-center">
                    <i class="fas fa-bolt text-white"></i>
                </div>
                <span class="hidden md:block">Scott<span class="text-blue-500">Sub</span></span>
            </a>

            <!-- Global Search -->
            <div class="hidden md:block flex-1 max-w-md mx-4 relative">
                <form action="index.php" method="GET">
                    <input type="hidden" name="module" value="shop">
                    <input type="hidden" name="page" value="search">
                    <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                    <input type="text" name="q" placeholder="Search products..." 
                           class="w-full bg-gray-900 border border-gray-700 rounded-full py-2 pl-10 pr-4 focus:outline-none focus:border-blue-500 transition text-sm">
                </form>
            </div>

            <!-- Right Actions -->
            <div class="flex items-center gap-3">
                
                <!-- Google Translate Widget -->
                <!-- The CSS above styles this to match the dark theme -->
                <div id="google_translate_element"></div>

                <!-- Currency Switcher -->
                <div class="hidden sm:flex items-center bg-gray-800 rounded-lg p-1 border border-gray-700 notranslate">
                    <a href="?set_curr=MMK" class="px-2 py-1.5 text-xs rounded font-bold <?php echo (isset($_SESSION['currency']) && $_SESSION['currency']=='MMK') ? 'bg-green-600 text-white' : 'text-gray-400 hover:text-white'; ?>">Ks</a>
                    <a href="?set_curr=USD" class="px-2 py-1.5 text-xs rounded font-bold <?php echo (isset($_SESSION['currency']) && $_SESSION['currency']=='USD') ? 'bg-green-600 text-white' : 'text-gray-400 hover:text-white'; ?>">$</a>
                </div>

                <!-- Notification Bell -->
                <?php if(isset($_SESSION['user_id'])): ?>
                    <div class="relative cursor-pointer hover:text-blue-400 transition group mx-1">
                        <i class="fas fa-bell text-lg"></i>
                        <span class="absolute -top-1 -right-1 w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                        <div class="absolute right-0 top-8 w-64 bg-gray-800 border border-gray-700 rounded-xl shadow-2xl p-4 hidden group-hover:block z-50">
                            <h4 class="text-sm font-bold border-b border-gray-700 pb-2 mb-2">Notifications</h4>
                            <div class="text-xs text-gray-400 text-center py-2">No new notifications</div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- User Menu -->
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="index.php?module=user&page=orders" class="bg-gray-800 hover:bg-gray-700 w-9 h-9 rounded-full flex items-center justify-center border border-gray-600 transition">
                        <i class="fas fa-user text-sm"></i>
                    </a>
                    <a href="index.php?module=auth&page=logout" class="text-red-400 hover:text-red-300 text-sm ml-1" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
                <?php else: ?>
                    <a href="index.php?module=auth&page=login" class="text-blue-400 font-bold text-sm hover:underline ml-2">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <!-- Mobile Search (Visible only on small screens) -->
    <div class="md:hidden bg-gray-800 p-2 border-b border-gray-700">
        <form action="index.php" method="GET" class="relative">
            <input type="hidden" name="module" value="shop">
            <input type="hidden" name="page" value="search">
            <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-xs"></i>
            <input type="text" name="q" placeholder="Search products..." 
                   class="w-full bg-gray-900 border border-gray-700 rounded-lg py-2 pl-9 pr-4 text-xs focus:outline-none focus:border-blue-500 text-white">
        </form>
    </div>

    <main class="flex-grow container mx-auto px-4 py-8">