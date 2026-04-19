<?php
// modules/home/index.php
// PRODUCTION READY v4.5 - Dynamic Live Telemetry, Clean Hub & Active Push Prompts

// 1. Fetch Banners (Active Slides)
$stmt = $pdo->query("SELECT * FROM banners ORDER BY display_order ASC, id DESC LIMIT 5");
$banners = $stmt->fetchAll();

// 2. Fetch Categories (Strictly using image_url)
$stmt = $pdo->query("SELECT id, name, image_url, description, type FROM categories ORDER BY id ASC");
$categories = $stmt->fetchAll();

// 3. Fetch "Hot Deals" (Sale Items)
$stmt = $pdo->query("
    SELECT p.*, c.name as cat_name, c.image_url as cat_image
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    WHERE p.sale_price IS NOT NULL AND p.sale_price < p.price
    ORDER BY RAND() LIMIT 4
");
$flash_sales = $stmt->fetchAll();

// 4. Fetch "Best Sellers" (Based on active orders)
$stmt = $pdo->query("
    SELECT p.*, c.name as cat_name, c.image_url as cat_image, COUNT(o.id) as sales_count
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    LEFT JOIN orders o ON p.id = o.product_id AND o.status = 'active'
    GROUP BY p.id
    ORDER BY sales_count DESC LIMIT 6
");
$best_sellers = $stmt->fetchAll();

// 5. Fetch "Recent Arrivals" (Main Grid)
$stmt = $pdo->query("
    SELECT p.*, c.name as cat_name, c.image_url as cat_image
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    ORDER BY p.id DESC LIMIT 18
");
$recent_products = $stmt->fetchAll();

// 6. Fetch "Community Trust" (Recent 5-star reviews)
$stmt = $pdo->query("
    SELECT r.*, u.username, p.name as product_name, p.id as pid, p.image_path as p_image
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    JOIN products p ON r.product_id = p.id
    WHERE r.rating >= 4
    ORDER BY r.created_at DESC LIMIT 4
");
$recent_reviews = $stmt->fetchAll();

// 7. Fetch Active Payment Methods for Display
$stmt = $pdo->query("SELECT bank_name, logo_class FROM payment_methods WHERE is_active = 1");
$active_payments = $stmt->fetchAll();

// 8. Get Current User Discount & Name
$discount = is_logged_in() ? get_user_discount($_SESSION['user_id']) : 0;
$first_name = is_logged_in() ? explode(' ', $_SESSION['user_name'])[0] : 'Operative';

// 9. Fetch Real Stats for Telemetry
$total_users_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_orders_count = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'active'")->fetchColumn();
$display_users = max($total_users_count, 1250); 
$display_orders = max($total_orders_count, 8500);

// Generate an initial random seed for the live online user counter (10 to 1000)
$live_online = rand(120, 850); 

// 10. Check Push Subscription Status (Failsafe for Admin Errors)
$is_subscribed = false;
if (is_logged_in()) {
    try {
        $sub_check = $pdo->prepare("SELECT id FROM push_subscriptions WHERE user_id = ?");
        $sub_check->execute([$_SESSION['user_id']]);
        $is_subscribed = $sub_check->rowCount() > 0;
    } catch (Exception $e) {} // Ignore if table doesn't exist yet
}
?>

<style>
    /* Custom Scroll & Animations */
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    
    .glass-card {
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(255, 255, 255, 0.05);
        box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .glass-card:hover {
        background: rgba(15, 23, 42, 0.8);
        border-color: rgba(0, 240, 255, 0.3);
        transform: translateY(-4px);
        box-shadow: 0 10px 25px -5px rgba(0, 240, 255, 0.15);
    }
    
    .animate-marquee { animation: marquee 30s linear infinite; }
    .animate-marquee-slow { animation: marquee 45s linear infinite; }
    @keyframes marquee { 0% { transform: translateX(100%); } 100% { transform: translateX(-100%); } }

    /* Slider Scroll Snapping */
    .snap-x-mandatory { scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch; }
    .snap-start { scroll-snap-align: start; }
    .snap-center { scroll-snap-align: center; }

    /* Progress Bar Animation */
    @keyframes loadProgress { 0% { width: 0%; } 100% { width: 100%; } }
    
    /* Cinematic Image Pan (Slide Show effect) */
    @keyframes panImage {
        0% { transform: scale(1.05) translate(0, 0); }
        50% { transform: scale(1.15) translate(-1%, -1%); }
        100% { transform: scale(1.05) translate(0, 0); }
    }
    .animate-pan-image {
        animation: panImage 20s ease-in-out infinite;
    }

    /* Smooth Infinite Marquee */
    @keyframes marquee-infinite {
        0% { transform: translateX(0); }
        100% { transform: translateX(-50%); }
    }
    .animate-marquee-infinite {
        animation: marquee-infinite 35s linear infinite;
        width: max-content;
    }
</style>

<!-- SECTION 0: News Ticker -->
<div class="w-full bg-slate-950 border-b border-[#00f0ff]/20 overflow-hidden py-1.5 mb-6 relative shadow-[0_4px_20px_rgba(0,240,255,0.05)]">
    <div class="absolute inset-y-0 left-0 w-16 bg-gradient-to-r from-slate-950 to-transparent z-10 pointer-events-none"></div>
    <div class="absolute inset-y-0 right-0 w-16 bg-gradient-to-l from-slate-950 to-transparent z-10 pointer-events-none"></div>
    <div class="whitespace-nowrap animate-marquee text-[10px] sm:text-xs text-[#00f0ff] font-mono tracking-[0.2em] uppercase font-bold">
        🚀 System Online • Encrypted Connections Active • Global Game Keys & Premium Deployments Available 24/7 • Instant Delivery Matrix 
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-12">
    
    <!-- User Greeting -->
    <div class="mb-6 flex items-center justify-between animate-fade-in-down">
        <h2 class="text-xl md:text-2xl font-black text-white tracking-tight">
            Welcome, <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-[#00f0ff]"><?php echo htmlspecialchars($first_name); ?></span>
        </h2>
        <div class="flex items-center gap-2 text-xs font-mono text-green-400 bg-green-500/10 px-3 py-1 rounded-full border border-green-500/20 shadow-[0_0_10px_rgba(34,197,94,0.2)] hidden sm:flex">
            <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse shadow-[0_0_8px_#22c55e]"></span> Network Stable
        </div>
    </div>

    <!-- PROACTIVE PUSH SUBSCRIPTION BANNER -->
    <?php if(is_logged_in() && !$is_subscribed): ?>
    <div class="mb-8 bg-red-900/20 border border-red-500/50 p-5 md:p-6 rounded-2xl shadow-[0_0_20px_rgba(239,68,68,0.2)] flex flex-col sm:flex-row items-center justify-between gap-4 relative overflow-hidden group">
        <div class="absolute -right-10 -top-10 w-32 h-32 bg-red-500/10 rounded-full blur-3xl pointer-events-none group-hover:bg-red-500/20 transition-colors"></div>
        <div class="flex items-center gap-4 relative z-10 w-full sm:w-auto">
            <div class="w-12 h-12 bg-red-500/20 rounded-full flex items-center justify-center text-red-500 border border-red-500/30 shrink-0 shadow-inner">
                <i class="fas fa-satellite-dish text-xl animate-pulse"></i>
            </div>
            <div>
                <h3 class="font-black text-white text-base md:text-lg tracking-tight">Comms Uplink Disconnected</h3>
                <p class="text-xs text-slate-400 leading-snug">Your device is not receiving Matrix Push Alerts. Enable them to receive instant delivery notifications.</p>
            </div>
        </div>
        <button onclick="initializeManualUplink(this)" class="w-full sm:w-auto shrink-0 bg-red-600 hover:bg-red-500 text-white font-bold px-6 py-3 rounded-xl shadow-[0_0_15px_rgba(239,68,68,0.4)] transition transform active:scale-95 text-xs uppercase tracking-widest relative z-10 flex items-center justify-center gap-2">
            <i class="fas fa-plug"></i> Establish Uplink
        </button>
    </div>
    <script>
        function initializeManualUplink(btn) {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Securing...';
            if (typeof window.registerServiceWorker === 'function') {
                window.registerServiceWorker(true).then(() => {
                    btn.closest('.bg-red-900\\/20').remove();
                    alert("Uplink Established. You will now receive secure transmissions.");
                }).catch(err => {
                    btn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Failed';
                    alert("Connection failed. Please click the padlock icon near your URL bar and allow Notifications, then try again.");
                });
            } else {
                alert("Matrix core script missing. Please refresh the page.");
                btn.innerHTML = '<i class="fas fa-plug"></i> Establish Uplink';
            }
        }
    </script>
    <?php endif; ?>

    <!-- SECTION 1: Cinematic Poster Banner Slider -->
    <?php if(!empty($banners)): ?>
    <div class="relative w-full aspect-[4/3] sm:aspect-video lg:max-h-[500px] mb-10 rounded-3xl overflow-hidden group shadow-[0_20px_50px_rgba(0,0,0,0.6)] bg-slate-900 border border-[#00f0ff]/30" id="heroSliderContainer">
        
        <div class="flex overflow-x-auto snap-x-mandatory h-full no-scrollbar scroll-smooth" id="bannerSlider">
            <?php foreach($banners as $index => $b): ?>
                <div class="w-full flex-shrink-0 snap-center relative h-full banner-slide bg-black" data-index="<?php echo $index; ?>">
                    <a href="<?php echo $b['target_url'] ?: '#'; ?>" target="<?php echo $b['target_url'] ? '_blank' : '_self'; ?>" class="block w-full h-full cursor-pointer overflow-hidden">
                        
                        <!-- Panning Image -->
                        <img src="<?php echo BASE_URL . $b['image_path']; ?>" class="w-full h-full object-cover animate-pan-image opacity-90 mix-blend-lighten" loading="lazy" alt="<?php echo htmlspecialchars($b['title']); ?>">
                        
                        <!-- Cinematic Gradients -->
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-900/30 to-transparent opacity-90"></div>
                        <div class="absolute inset-0 bg-blue-900/20 mix-blend-color-burn pointer-events-none"></div>
                        
                        <!-- Banner Text -->
                        <div class="absolute bottom-0 left-0 p-6 sm:p-12 w-full z-10">
                            <span class="text-[10px] text-[#00f0ff] font-black uppercase tracking-[0.3em] mb-3 block drop-shadow-md bg-slate-900/50 backdrop-blur-md px-3 py-1 rounded w-fit border border-[#00f0ff]/30">Featured Node</span>
                            <h3 class="text-white text-3xl sm:text-5xl md:text-6xl lg:text-7xl font-black drop-shadow-[0_0_20px_rgba(0,240,255,0.6)] mb-4 transform transition-transform duration-500 translate-y-0 group-hover:-translate-y-2 tracking-tighter leading-none max-w-4xl"><?php echo htmlspecialchars($b['title']); ?></h3>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Progress Bar Indicator -->
        <div class="absolute bottom-0 left-0 w-full h-1.5 bg-slate-800/80 z-20">
            <div id="slideProgress" class="h-full bg-gradient-to-r from-blue-600 via-[#00f0ff] to-white shadow-[0_0_15px_#00f0ff] w-0"></div>
        </div>

        <!-- Navigation Dots -->
        <div class="absolute bottom-8 right-8 flex gap-3 z-20" id="sliderDots">
            <?php foreach($banners as $i => $b): ?>
                <button class="w-3 h-3 rounded-full transition-all duration-300 slider-dot <?php echo $i === 0 ? 'bg-[#00f0ff] shadow-[0_0_15px_rgba(0,240,255,1)] w-10' : 'bg-white/40 hover:bg-white/80'; ?>" data-target="<?php echo $i; ?>"></button>
            <?php endforeach; ?>
        </div>
        
        <!-- Arrow Controls -->
        <button id="prevSlide" class="absolute left-6 top-1/2 -translate-y-1/2 w-14 h-14 rounded-2xl bg-slate-900/60 backdrop-blur-xl border border-white/10 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-300 hover:bg-[#00f0ff] hover:text-slate-900 hover:scale-110 shadow-[0_0_30px_rgba(0,0,0,0.5)]"><i class="fas fa-chevron-left text-xl"></i></button>
        <button id="nextSlide" class="absolute right-6 top-1/2 -translate-y-1/2 w-14 h-14 rounded-2xl bg-slate-900/60 backdrop-blur-xl border border-white/10 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-300 hover:bg-[#00f0ff] hover:text-slate-900 hover:scale-110 shadow-[0_0_30px_rgba(0,0,0,0.5)]"><i class="fas fa-chevron-right text-xl"></i></button>
    </div>
    <?php endif; ?>

    <!-- SECTION 1.5: System Telemetry (Dynamic Social Proof Matrix) -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6 mb-14 relative z-10">
        
        <!-- Uptime -->
        <div class="bg-slate-900/80 backdrop-blur-xl border border-blue-500/20 rounded-3xl p-5 text-center group hover:border-[#00f0ff]/50 transition-all duration-300 shadow-[0_0_15px_rgba(0,0,0,0.5)] hover:shadow-[0_0_25px_rgba(0,240,255,0.15)] relative overflow-hidden">
            <div class="absolute -left-6 -top-6 w-20 h-20 bg-blue-500/10 rounded-full blur-2xl group-hover:bg-[#00f0ff]/20 transition-colors"></div>
            <i class="fas fa-server text-2xl text-slate-500 group-hover:text-[#00f0ff] mb-2 transition-colors relative z-10"></i>
            <div class="text-2xl md:text-3xl font-black text-white tracking-tighter relative z-10"><span class="telemetry-counter" data-target="99.99" data-suffix="%">0</span></div>
            <div class="text-[9px] md:text-[10px] text-slate-400 uppercase tracking-widest font-bold mt-1 relative z-10">Server Uptime</div>
        </div>
        
        <!-- Live Connections (Dynamic Fluctuation) -->
        <div class="bg-slate-900/80 backdrop-blur-xl border border-purple-500/20 rounded-3xl p-5 text-center group hover:border-purple-500/50 transition-all duration-300 shadow-[0_0_15px_rgba(0,0,0,0.5)] hover:shadow-[0_0_25px_rgba(168,85,247,0.15)] relative overflow-hidden">
            <div class="absolute -right-6 -bottom-6 w-20 h-20 bg-purple-500/10 rounded-full blur-2xl group-hover:bg-purple-500/20 transition-colors"></div>
            <div class="absolute top-4 right-4 flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse shadow-[0_0_5px_#22c55e]"></span>
                <span class="text-[8px] text-green-400 font-bold uppercase tracking-widest">Live</span>
            </div>
            <i class="fas fa-network-wired text-2xl text-slate-500 group-hover:text-purple-400 mb-2 transition-colors relative z-10"></i>
            <div class="text-2xl md:text-3xl font-black text-white tracking-tighter relative z-10"><span id="liveUsersCounter" class="text-purple-300 drop-shadow-[0_0_8px_rgba(168,85,247,0.5)] transition-colors duration-300"><?php echo number_format($live_online); ?></span></div>
            <div class="text-[9px] md:text-[10px] text-slate-400 uppercase tracking-widest font-bold mt-1 relative z-10">Active Connections</div>
        </div>

        <!-- Deliveries (Live Ticker) -->
        <div class="bg-slate-900/80 backdrop-blur-xl border border-green-500/20 rounded-3xl p-5 text-center group hover:border-green-500/50 transition-all duration-300 shadow-[0_0_15px_rgba(0,0,0,0.5)] hover:shadow-[0_0_25px_rgba(34,197,94,0.15)] relative overflow-hidden" id="deliveriesCard">
            <div class="absolute -left-6 -bottom-6 w-20 h-20 bg-green-500/10 rounded-full blur-2xl group-hover:bg-green-500/20 transition-colors"></div>
            <i class="fas fa-parachute-box text-2xl text-slate-500 group-hover:text-green-400 mb-2 transition-colors relative z-10"></i>
            <div class="text-2xl md:text-3xl font-black text-white tracking-tighter relative z-10"><span id="liveDeliveriesCounter" class="telemetry-counter text-green-300 transition-colors duration-300" data-target="<?php echo $display_orders; ?>" data-suffix="+">0</span></div>
            <div class="text-[9px] md:text-[10px] text-slate-400 uppercase tracking-widest font-bold mt-1 relative z-10">Assets Deployed</div>
        </div>

        <!-- System Status -->
        <div class="bg-slate-900/80 backdrop-blur-xl border border-yellow-500/20 rounded-3xl p-5 text-center group hover:border-yellow-500/50 transition-all duration-300 shadow-[0_0_15px_rgba(0,0,0,0.5)] hover:shadow-[0_0_25px_rgba(234,179,8,0.15)] relative overflow-hidden">
            <div class="absolute -right-6 -top-6 w-20 h-20 bg-yellow-500/10 rounded-full blur-2xl group-hover:bg-yellow-500/20 transition-colors"></div>
            <i class="fas fa-shield-check text-2xl text-slate-500 group-hover:text-yellow-400 mb-2 transition-colors relative z-10"></i>
            <div class="text-2xl md:text-3xl font-black text-white tracking-tighter relative z-10"><span class="telemetry-counter text-yellow-300" data-target="24" data-suffix="/7">0</span></div>
            <div class="text-[9px] md:text-[10px] text-slate-400 uppercase tracking-widest font-bold mt-1 relative z-10">Automated Protocol</div>
        </div>
    </div>

    <!-- SECTION 2: Interactive Category Slider -->
    <div class="mb-14 relative">
        <div class="flex items-end justify-between mb-6">
            <h2 class="text-2xl md:text-3xl font-black text-white tracking-tight flex items-center gap-3">
                <i class="fas fa-network-wired text-[#00f0ff]"></i> Sector Directory
            </h2>
            <div class="hidden sm:flex gap-2">
                <button id="catPrev" class="w-10 h-10 rounded-xl bg-slate-800 hover:bg-[#00f0ff] text-slate-400 hover:text-slate-900 transition flex items-center justify-center shadow-lg"><i class="fas fa-angle-left"></i></button>
                <button id="catNext" class="w-10 h-10 rounded-xl bg-slate-800 hover:bg-[#00f0ff] text-slate-400 hover:text-slate-900 transition flex items-center justify-center shadow-lg"><i class="fas fa-angle-right"></i></button>
            </div>
        </div>

        <!-- Slider Container -->
        <div class="relative group">
            <div id="categorySlider" class="flex overflow-x-auto snap-x-mandatory gap-4 sm:gap-6 pb-6 pt-2 px-2 -mx-2 no-scrollbar scroll-smooth">
                <?php foreach($categories as $cat): ?>
                    <a href="index.php?module=shop&page=category&id=<?php echo $cat['id']; ?>" class="snap-start shrink-0 w-[140px] sm:w-[180px] lg:w-[240px] glass-card rounded-3xl overflow-hidden group/cat relative flex flex-col justify-end aspect-[4/5] border border-slate-700/50 hover:border-[#00f0ff]/50">
                        
                        <!-- Dynamic Background -->
                        <?php if(!empty($cat['image_url'])): ?>
                            <img src="<?php echo BASE_URL . $cat['image_url']; ?>" alt="<?php echo htmlspecialchars($cat['name']); ?>" class="absolute inset-0 w-full h-full object-cover opacity-60 group-hover/cat:opacity-100 transition-all duration-700 group-hover/cat:scale-110">
                        <?php else: ?>
                            <div class="absolute inset-0 bg-slate-800 flex items-center justify-center group-hover/cat:scale-110 transition duration-700 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iMiIgY3k9IjIiIHI9IjEiIGZpbGw9InJnYmEoMCwgMjQwLCAyNTUsIDAuMikiLz48L3N2Zz4=')] opacity-50"></div>
                        <?php endif; ?>

                        <!-- Overlays -->
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-900/60 to-transparent opacity-90 group-hover/cat:opacity-70 transition-opacity"></div>
                        
                        <!-- Hover Glow -->
                        <div class="absolute -bottom-10 left-1/2 -translate-x-1/2 w-32 h-32 bg-[#00f0ff]/40 rounded-full blur-3xl opacity-0 group-hover/cat:opacity-100 transition-opacity duration-500 pointer-events-none"></div>

                        <!-- Content -->
                        <div class="relative z-10 p-5 md:p-6 text-center w-full transform transition-transform duration-300 group-hover/cat:-translate-y-2">
                            <span class="inline-block px-2.5 py-1 bg-slate-900/80 border border-slate-700 rounded text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3 backdrop-blur-md shadow-lg">
                                <?php echo htmlspecialchars($cat['type']); ?>
                            </span>
                            <h3 class="text-base sm:text-lg font-black text-white uppercase tracking-wider drop-shadow-lg leading-tight"><?php echo htmlspecialchars($cat['name']); ?></h3>
                            <!-- Hover Reveal Text -->
                            <div class="h-0 overflow-hidden group-hover/cat:h-auto group-hover/cat:mt-3 transition-all duration-300">
                                <p class="text-[10px] text-slate-300 line-clamp-2 leading-relaxed font-bold uppercase tracking-widest">Enter Sector &rarr;</p>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Horizontal Scroll Progress Line -->
            <div class="absolute -bottom-2 left-0 w-full h-1.5 bg-slate-800/50 rounded-full overflow-hidden backdrop-blur">
                <div id="catScrollProgress" class="h-full bg-gradient-to-r from-blue-600 to-[#00f0ff] rounded-full w-0 transition-all duration-150 shadow-[0_0_10px_#00f0ff]"></div>
            </div>
        </div>
    </div>

    <!-- SECTION 3: Hot Deals (Flash Sales) -->
    <?php if(!empty($flash_sales)): ?>
    <div class="mb-16">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-3">
            <div class="flex items-center gap-3">
                <span class="relative flex h-5 w-5">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-5 w-5 bg-red-500 shadow-[0_0_15px_rgba(239,68,68,0.8)]"></span>
                </span>
                <h2 class="text-2xl md:text-3xl font-black text-white tracking-tight">Flash Sales</h2>
            </div>
            <!-- Dynamic Countdown visual -->
            <div class="text-[10px] md:text-xs font-mono text-red-400 font-bold bg-red-900/20 px-4 py-2 rounded-xl border border-red-900/50 uppercase tracking-widest w-fit flex items-center gap-2 shadow-inner">
                <i class="fas fa-stopwatch animate-pulse"></i> Deals Expiring Soon
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php foreach($flash_sales as $product): 
                include __DIR__ . '/product_card.php'; 
            endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- SECTION 4: Feature Strip (Circuit Node Style) -->
    <div class="mb-16 relative mt-10">
        <!-- Connecting Circuit Line Background -->
        <div class="absolute top-1/2 left-10 right-10 h-[2px] bg-slate-800 -translate-y-1/2 hidden md:block overflow-hidden rounded-full z-0">
            <div class="h-full w-1/3 bg-gradient-to-r from-transparent via-[#00f0ff] to-transparent animate-[marquee_3s_linear_infinite]"></div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-8 relative z-10">
            <!-- 1. Instant Delivery (Focus Node) -->
            <div class="bg-slate-900/90 backdrop-blur-xl border border-green-500/40 p-6 md:p-8 rounded-3xl flex flex-col items-center text-center gap-4 relative overflow-hidden group shadow-[0_10px_30px_rgba(34,197,94,0.15)] hover:shadow-[0_15px_40px_rgba(34,197,94,0.3)] hover:border-green-400 transition-all duration-500 transform hover:-translate-y-1.5">
                <!-- Inner Dotted Grid on Hover -->
                <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iMiIgY3k9IjIiIHI9IjEiIGZpbGw9InJnYmEoMzQsIDE5NywgOTQsIDAuMikiLz48L3N2Zz4=')] opacity-0 group-hover:opacity-100 transition-opacity duration-500 z-0"></div>
                <div class="absolute -top-10 -right-10 w-32 h-32 bg-green-500/20 rounded-full blur-3xl group-hover:bg-green-500/40 transition-colors duration-500 z-0"></div>
                
                <div class="w-20 h-20 rounded-2xl bg-slate-950 flex items-center justify-center text-green-400 text-4xl border border-green-500/50 shadow-[inset_0_0_20px_rgba(34,197,94,0.2),0_0_25px_rgba(34,197,94,0.3)] group-hover:scale-110 transition-transform duration-500 relative z-10">
                    <i class="fas fa-bolt drop-shadow-[0_0_15px_rgba(34,197,94,0.8)] animate-pulse"></i>
                </div>
                
                <div class="relative z-10 w-full">
                    <div class="text-sm font-black text-white tracking-widest uppercase mb-2 group-hover:text-green-400 transition-colors">Instant Delivery</div>
                    <div class="text-[10px] md:text-xs text-slate-400 font-medium leading-relaxed bg-slate-950/60 px-4 py-2.5 rounded-xl border border-slate-800 shadow-inner">24/7 Automated Matrix Dispenser.<br>Zero Latency.</div>
                </div>
            </div>

            <!-- 2. Official Warranty -->
            <div class="bg-slate-900/90 backdrop-blur-xl border border-[#00f0ff]/30 p-6 md:p-8 rounded-3xl flex flex-col items-center text-center gap-4 relative overflow-hidden group shadow-[0_10px_30px_rgba(0,240,255,0.1)] hover:shadow-[0_15px_40px_rgba(0,240,255,0.2)] hover:border-[#00f0ff] transition-all duration-500 transform hover:-translate-y-1.5">
                <!-- Inner Dotted Grid on Hover -->
                <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iMiIgY3k9IjIiIHI9IjEiIGZpbGw9InJnYmEoMCwgMjQwLCAyNTUsIDAuMikiLz48L3N2Zz4=')] opacity-0 group-hover:opacity-100 transition-opacity duration-500 z-0"></div>
                <div class="absolute -top-10 -right-10 w-32 h-32 bg-[#00f0ff]/20 rounded-full blur-3xl group-hover:bg-[#00f0ff]/30 transition-colors duration-500 z-0"></div>
                
                <div class="w-20 h-20 rounded-2xl bg-slate-950 flex items-center justify-center text-[#00f0ff] text-4xl border border-[#00f0ff]/40 shadow-[inset_0_0_15px_rgba(0,240,255,0.2),0_0_20px_rgba(0,240,255,0.2)] group-hover:scale-110 transition-transform duration-500 relative z-10">
                    <i class="fas fa-alt-check drop-shadow-[0_0_15px_rgba(0,240,255,0.8)]"></i>
                </div>
                
                <div class="relative z-10 w-full">
                    <div class="text-sm font-black text-white tracking-widest uppercase mb-2 group-hover:text-[#00f0ff] transition-colors">Official Warranty</div>
                    <div class="text-[10px] md:text-xs text-slate-400 font-medium leading-relaxed bg-slate-950/60 px-4 py-2.5 rounded-xl border border-slate-800 shadow-inner">Secure Encrypted Protocol.<br>100% Guaranteed.</div>
                </div>
            </div>

            <!-- 3. Local Payment -->
            <div class="bg-slate-900/90 backdrop-blur-xl border border-yellow-500/30 p-6 md:p-8 rounded-3xl flex flex-col items-center text-center gap-4 relative overflow-hidden group shadow-[0_10px_30px_rgba(234,179,8,0.1)] hover:shadow-[0_15px_40px_rgba(234,179,8,0.2)] hover:border-yellow-400 transition-all duration-500 transform hover:-translate-y-1.5">
                <!-- Inner Dotted Grid on Hover -->
                <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iMiIgY3k9IjIiIHI9IjEiIGZpbGw9InJnYmEoMjM0LCAxNzksIDgsIDAuMikiLz48L3N2Zz4=')] opacity-0 group-hover:opacity-100 transition-opacity duration-500 z-0"></div>
                <div class="absolute -top-10 -right-10 w-32 h-32 bg-yellow-500/20 rounded-full blur-3xl group-hover:bg-yellow-500/30 transition-colors duration-500 z-0"></div>
                
                <div class="w-20 h-20 rounded-2xl bg-slate-950 flex items-center justify-center text-yellow-400 text-4xl border border-yellow-500/40 shadow-[inset_0_0_15px_rgba(234,179,8,0.2),0_0_20px_rgba(234,179,8,0.2)] group-hover:scale-110 transition-transform duration-500 relative z-10">
                    <i class="fas fa-wallet drop-shadow-[0_0_15px_rgba(234,179,8,0.8)]"></i>
                </div>
                
                <div class="relative z-10 w-full">
                    <div class="text-sm font-black text-white tracking-widest uppercase mb-2 group-hover:text-yellow-400 transition-colors">Local Payment</div>
                    <div class="text-[10px] md:text-xs text-slate-400 font-medium leading-relaxed bg-slate-950/60 px-4 py-2.5 rounded-xl border border-slate-800 shadow-inner">KPay & Wave Pay Ecosystems<br>Fully Linked.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 5: Main Hub (Sidebar + Grid) -->
    <div class="grid grid-cols-1 xl:grid-cols-4 gap-8 mb-16">
        
        <!-- LEFT SIDEBAR: Trending & Reviews -->
        <div class="xl:col-span-1 space-y-8">
            
            <!-- Trending -->
            <div class="glass border border-slate-700/50 rounded-3xl p-6 md:p-8 shadow-2xl relative overflow-hidden">
                <div class="absolute top-0 right-0 w-40 h-40 bg-orange-500/10 rounded-full blur-3xl pointer-events-none"></div>
                <h3 class="text-xl font-black text-white flex items-center gap-3 mb-8 uppercase tracking-wider relative z-10">
                    <i class="fas fa-fire text-orange-500 animate-pulse text-2xl"></i> Trending
                </h3>
                <div class="space-y-6 relative z-10">
                    <?php foreach($best_sellers as $product): ?>
                        <?php 
                            $b_base = $product['sale_price'] ?: $product['price'];
                            $b_final = $b_base * ((100 - $discount) / 100);
                        ?>
                        <a href="index.php?module=shop&page=product&id=<?php echo $product['id']; ?>" class="flex items-center gap-4 group">
                            <div class="w-16 h-16 rounded-2xl bg-slate-900 flex items-center justify-center text-[#00f0ff] border border-slate-700 shrink-0 group-hover:border-[#00f0ff]/50 transition-colors overflow-hidden shadow-inner relative">
                                <?php if(!empty($product['image_path'])): ?>
                                    <img src="<?php echo BASE_URL . $product['image_path']; ?>" class="w-full h-full object-cover">
                                <?php elseif(!empty($product['cat_image'])): ?>
                                    <img src="<?php echo BASE_URL . $product['cat_image']; ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <i class="fas fa-box text-2xl opacity-50"></i>
                                <?php endif; ?>
                            </div>
                            
                            <div class="min-w-0 flex-1">
                                <h4 class="text-sm font-bold text-slate-200 truncate group-hover:text-[#00f0ff] transition-colors"><?php echo htmlspecialchars($product['name']); ?></h4>
                                <div class="flex items-center gap-2 text-[10px] mt-1.5">
                                    <span class="text-green-400 font-mono font-bold bg-green-900/20 px-2 py-0.5 rounded border border-green-500/20"><?php echo format_price($b_final); ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Network Comms (Reviews) -->
            <?php if(!empty($recent_reviews)): ?>
            <div class="glass border border-slate-700/50 rounded-3xl p-6 md:p-8 shadow-2xl relative overflow-hidden">
                <div class="absolute bottom-0 right-0 w-40 h-40 bg-[#00f0ff]/5 rounded-full blur-3xl pointer-events-none"></div>
                <h3 class="text-xl font-black text-white mb-6 flex items-center gap-3 uppercase tracking-wider relative z-10">
                    <i class="fas fa-satellite-dish text-[#00f0ff]"></i> Live Comms
                </h3>
                <div class="space-y-4 relative z-10">
                    <?php foreach($recent_reviews as $r): ?>
                        <a href="index.php?module=shop&page=product&id=<?php echo $r['pid']; ?>" class="block p-5 rounded-2xl bg-slate-900/50 border border-slate-700/50 hover:border-[#00f0ff]/30 transition group shadow-inner">
                            <div class="flex items-center gap-3 mb-3">
                                <?php if(!empty($r['p_image'])): ?>
                                    <img src="<?php echo BASE_URL . $r['p_image']; ?>" class="w-8 h-8 rounded-lg object-cover border border-slate-600 shrink-0">
                                <?php else: ?>
                                    <div class="w-8 h-8 rounded-lg bg-slate-800 flex items-center justify-center text-slate-500 text-xs shrink-0"><i class="fas fa-box"></i></div>
                                <?php endif; ?>
                                <div class="min-w-0">
                                    <p class="text-[10px] font-bold text-slate-300 group-hover:text-white transition truncate">@<?php echo htmlspecialchars($r['username']); ?></p>
                                    <div class="flex text-[8px] text-yellow-500 mt-0.5">
                                        <?php for($i=0; $i<$r['rating']; $i++) echo '<i class="fas fa-star"></i>'; ?>
                                    </div>
                                </div>
                            </div>
                            <p class="text-xs text-slate-400 italic line-clamp-3 leading-relaxed font-medium">"<?php echo htmlspecialchars($r['comment']); ?>"</p>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <!-- RIGHT MAIN GRID: New Arrivals -->
        <div class="xl:col-span-3">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end mb-6 gap-3">
                <div>
                    <h2 class="text-3xl md:text-4xl font-black text-white mb-2 tracking-tight">New Deployments</h2>
                    <p class="text-slate-400 text-sm font-medium">Fresh data injected into the matrix</p>
                </div>
                <a href="index.php?module=shop&page=search" class="text-[#00f0ff] hover:text-slate-900 text-sm font-black flex items-center gap-2 transition-all uppercase tracking-widest bg-[#00f0ff]/10 hover:bg-[#00f0ff] px-5 py-3 rounded-xl border border-[#00f0ff]/30 shadow-lg">
                    View Full Matrix <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach($recent_products as $product): 
                    include __DIR__ . '/product_card.php'; 
                endforeach; ?>
            </div>
        </div>
    </div>

    <!-- SECTION 6: Payment Methods Directory -->
    <?php if(!empty($active_payments)): ?>
    <div class="mt-10 border-t border-slate-800 pt-10">
        <div class="text-center mb-8 relative">
            <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-32 h-32 bg-[#00f0ff]/5 rounded-full blur-2xl pointer-events-none"></div>
            <h3 class="text-xl md:text-2xl font-black text-white tracking-tight flex items-center justify-center gap-3 relative z-10">
                <i class="fas fa-link text-[#00f0ff]"></i> Financial Interlinks
            </h3>
            <p class="text-slate-400 text-xs font-medium uppercase tracking-widest mt-2 relative z-10">Secure Gateway Integrations</p>
        </div>

        <div class="relative w-full overflow-hidden py-4 group/payment">
            <!-- Fade Edges -->
            <div class="absolute inset-y-0 left-0 w-12 md:w-32 bg-gradient-to-r from-[#0f172a] to-transparent z-10 pointer-events-none"></div>
            <div class="absolute inset-y-0 right-0 w-12 md:w-32 bg-gradient-to-l from-[#0f172a] to-transparent z-10 pointer-events-none"></div>
            
            <div class="flex overflow-x-auto gap-4 md:gap-6 snap-x-mandatory hide-scrollbar pl-4 md:pl-[20%] pr-4 md:pr-[20%]">
                <?php foreach($active_payments as $pm): ?>
                    <div class="snap-center shrink-0 w-[200px] md:w-[240px] bg-slate-900/60 backdrop-blur border border-slate-700/50 hover:border-[#00f0ff]/40 rounded-2xl p-4 flex items-center gap-4 transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_10px_20px_rgba(0,240,255,0.1)]">
                        <div class="w-12 h-12 rounded-xl bg-slate-800 flex items-center justify-center border border-slate-600 shrink-0 text-[#00f0ff] shadow-inner">
                            <i class="<?php echo htmlspecialchars($pm['logo_class']); ?> text-xl"></i>
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-sm font-bold text-white tracking-wide truncate"><?php echo htmlspecialchars($pm['bank_name']); ?></h4>
                            <p class="text-[9px] text-green-400 uppercase tracking-widest font-bold mt-0.5 flex items-center gap-1"><i class="fas fa-check-circle"></i> Verified</p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <p class="text-center text-[10px] text-slate-500 font-mono mt-6 flex items-center justify-center gap-2">
                <i class="fas fa-arrows-alt-h"></i> Swipe to explore nodes
            </p>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- SCRIPTS -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    
    // --- 0. TELEMETRY COUNTER ANIMATION ---
    const counters = document.querySelectorAll('.telemetry-counter');
    const speed = 100;
    
    counters.forEach(counter => {
        const updateCount = () => {
            const target = +counter.getAttribute('data-target');
            const count = +counter.innerText;
            const inc = target / speed;
            
            if (count < target) {
                counter.innerText = Math.ceil(count + inc);
                setTimeout(updateCount, 15);
            } else {
                counter.innerText = target + (counter.getAttribute('data-suffix') || '');
            }
        };
        
        const observer = new IntersectionObserver((entries) => {
            if(entries[0].isIntersecting) {
                updateCount();
                observer.disconnect();
            }
        });
        observer.observe(counter);
    });

    // --- 1. HERO BANNER SLIDER ---
    const hSlider = document.getElementById('bannerSlider');
    const hDots = document.querySelectorAll('.slider-dot');
    const hProgress = document.getElementById('slideProgress');
    let hInterval;
    const hTime = 6000; // 6 seconds to appreciate the slow pan

    const updateHero = (index) => {
        hDots.forEach((dot, i) => {
            dot.className = i === index 
                ? 'w-10 h-3 rounded-full transition-all duration-300 slider-dot bg-[#00f0ff] shadow-[0_0_15px_rgba(0,240,255,1)]' 
                : 'w-3 h-3 rounded-full transition-all duration-300 slider-dot bg-white/40 hover:bg-white/80';
        });
        
        if(hProgress) {
            hProgress.style.animation = 'none';
            hProgress.offsetHeight; /* trigger reflow */
            hProgress.style.animation = `loadProgress ${hTime}ms linear forwards`;
        }
    };

    const moveHero = (next = true) => {
        if(!hSlider) return;
        const w = hSlider.clientWidth;
        let target = hSlider.scrollLeft + (next ? w : -w);
        if(target >= hSlider.scrollWidth - 10) target = 0;
        if(target < 0) target = hSlider.scrollWidth - w;
        
        hSlider.scrollTo({ left: target, behavior: 'smooth' });
        updateHero(Math.round(target / w));
    };

    const startHero = () => {
        if(hProgress) hProgress.style.animation = `loadProgress ${hTime}ms linear forwards`;
        hInterval = setInterval(() => moveHero(true), hTime);
    };

    if(hSlider) {
        document.getElementById('nextSlide')?.addEventListener('click', () => { clearInterval(hInterval); moveHero(true); startHero(); });
        document.getElementById('prevSlide')?.addEventListener('click', () => { clearInterval(hInterval); moveHero(false); startHero(); });
        
        hDots.forEach((dot, idx) => {
            dot.addEventListener('click', () => {
                clearInterval(hInterval);
                hSlider.scrollTo({ left: idx * hSlider.clientWidth, behavior: 'smooth' });
                updateHero(idx);
                startHero();
            });
        });
        
        hSlider.addEventListener('scroll', () => {
            clearTimeout(window.scrollTimeout);
            window.scrollTimeout = setTimeout(() => {
                updateHero(Math.round(hSlider.scrollLeft / hSlider.clientWidth));
            }, 100);
        });
        
        startHero();
    }

    // --- 2. CATEGORY SLIDER PROGRESS ---
    const cSlider = document.getElementById('categorySlider');
    const cProgress = document.getElementById('catScrollProgress');
    
    if(cSlider && cProgress) {
        const updateCatProgress = () => {
            const scrollPx = cSlider.scrollTop || cSlider.scrollLeft;
            const maxScroll = cSlider.scrollWidth - cSlider.clientWidth;
            const percentage = (scrollPx / maxScroll) * 100;
            cProgress.style.width = percentage + '%';
        };
        
        cSlider.addEventListener('scroll', updateCatProgress);
        updateCatProgress(); // init
        
        document.getElementById('catNext')?.addEventListener('click', () => {
            cSlider.scrollBy({ left: 300, behavior: 'smooth' });
        });
        document.getElementById('catPrev')?.addEventListener('click', () => {
            cSlider.scrollBy({ left: -300, behavior: 'smooth' });
        });
    }

    // --- 3. DYNAMIC LIVE TELEMETRY ENGINE ---
    
    // Live User Fluctuation
    const liveUsersEl = document.getElementById('liveUsersCounter');
    if (liveUsersEl) {
        let currentUsers = parseInt(liveUsersEl.innerText.replace(/,/g, '')) || 342;
        setInterval(() => {
            // Fluctuate by -3 to +5
            const change = Math.floor(Math.random() * 9) - 3;
            currentUsers += change;
            
            // Keep bounds realistic (10 to 1000)
            if (currentUsers < 10) currentUsers = 10 + Math.floor(Math.random() * 10);
            if (currentUsers > 1000) currentUsers = 1000 - Math.floor(Math.random() * 20);
            
            liveUsersEl.innerText = currentUsers.toLocaleString();
            
            // Pulse effect
            liveUsersEl.classList.add('text-white', 'scale-110');
            setTimeout(() => liveUsersEl.classList.remove('text-white', 'scale-110'), 300);
        }, 3500); // Every 3.5 seconds
    }

    // Live Delivery Ticker
    const deliveriesCard = document.getElementById('deliveriesCard');
    const deliveriesEl = document.getElementById('liveDeliveriesCounter');
    if (deliveriesEl && deliveriesCard) {
        setInterval(() => {
            // Randomly increment to simulate live purchases
            if(Math.random() > 0.5) {
                let currentDel = parseInt(deliveriesEl.innerText.replace(/[^0-9]/g, '')) || 8500;
                currentDel += 1;
                deliveriesEl.innerText = currentDel.toLocaleString() + '+';
                
                // Neon Flash effect on the whole card
                deliveriesCard.classList.remove('border-green-500/20');
                deliveriesCard.classList.add('border-green-400', 'shadow-[0_0_30px_rgba(34,197,94,0.4)]');
                
                setTimeout(() => {
                    deliveriesCard.classList.add('border-green-500/20');
                    deliveriesCard.classList.remove('border-green-400', 'shadow-[0_0_30px_rgba(34,197,94,0.4)]');
                }, 800);
            }
        }, 6000); // Check every 6 seconds
    }
});
</script>