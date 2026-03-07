<?php
// modules/home/index.php
// PRODUCTION READY v2.1 - Added Telemetry Counters & Trust Marquees

// 1. Fetch Banners (Active Slides)
$stmt = $pdo->query("SELECT * FROM banners ORDER BY display_order ASC, id DESC LIMIT 5");
$banners = $stmt->fetchAll();

// 2. Fetch Categories (REMOVED icon_class, strictly using image_url)
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
    ORDER BY sales_count DESC LIMIT 5
");
$best_sellers = $stmt->fetchAll();

// 5. Fetch "Recent Arrivals" (Main Grid)
$stmt = $pdo->query("
    SELECT p.*, c.name as cat_name, c.image_url as cat_image
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    ORDER BY p.id DESC LIMIT 12
");
$recent_products = $stmt->fetchAll();

// 6. Fetch "Community Trust" (Recent 5-star reviews)
$stmt = $pdo->query("
    SELECT r.*, u.username, p.name as product_name, p.id as pid
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    JOIN products p ON r.product_id = p.id
    WHERE r.rating >= 4
    ORDER BY r.created_at DESC LIMIT 3
");
$recent_reviews = $stmt->fetchAll();

// 7. Get Current User Discount & Name
$discount = is_logged_in() ? get_user_discount($_SESSION['user_id']) : 0;
$first_name = is_logged_in() ? explode(' ', $_SESSION['user_name'])[0] : 'Operative';

// 8. Generate Mock Data for Live Purchase Notifications (Social Proof)
$live_purchases_json = json_encode(array_map(function($p) {
    $names = ['Alex***', 'Ste***', 'Kyaw***', 'Zin***', 'Min***', 'Aung***', 'Lin***'];
    return [
        'user' => $names[array_rand($names)],
        'item' => $p['name'],
        'time' => rand(1, 59) . ' mins ago'
    ];
}, array_slice($best_sellers, 0, 4)));

// 9. Fetch Real Stats for Telemetry
$total_users_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_orders_count = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'active'")->fetchColumn();
// Base offsets to make it look active even if site is new
$display_users = max($total_users_count, 1250); 
$display_orders = max($total_orders_count, 8500);
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
    @keyframes loadProgress {
        0% { width: 0%; }
        100% { width: 100%; }
    }
    
    /* Live Notification Toast */
    .toast-enter { animation: toastSlideIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards; }
    .toast-exit { animation: toastSlideOut 0.5s ease-in forwards; }
    @keyframes toastSlideIn { from { transform: translateX(-100%) translateY(20px); opacity: 0; } to { transform: translateX(0) translateY(0); opacity: 1; } }
    @keyframes toastSlideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(-100%); opacity: 0; } }
</style>

<!-- SECTION 0: News Ticker -->
<div class="w-full bg-slate-950 border-b border-[#00f0ff]/20 overflow-hidden py-1.5 mb-6 relative shadow-[0_4px_20px_rgba(0,240,255,0.05)]">
    <div class="absolute inset-y-0 left-0 w-16 bg-gradient-to-r from-slate-950 to-transparent z-10 pointer-events-none"></div>
    <div class="absolute inset-y-0 right-0 w-16 bg-gradient-to-l from-slate-950 to-transparent z-10 pointer-events-none"></div>
    <div class="whitespace-nowrap animate-marquee text-[10px] sm:text-xs text-[#00f0ff] font-mono tracking-[0.2em] uppercase font-bold">
        🚀 System Online • Encrypted Connections Active • Global Game Keys & Premium Deployments Available 24/7 • Instant Delivery Matrix 
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    
    <!-- User Greeting -->
    <div class="mb-6 flex items-center justify-between animate-fade-in-down">
        <h2 class="text-xl md:text-2xl font-black text-white tracking-tight">
            Welcome, <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-[#00f0ff]"><?php echo htmlspecialchars($first_name); ?></span>
        </h2>
        <div class="flex items-center gap-2 text-xs font-mono text-green-400 bg-green-500/10 px-3 py-1 rounded-full border border-green-500/20 shadow-[0_0_10px_rgba(34,197,94,0.2)]">
            <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse shadow-[0_0_8px_#22c55e]"></span> Network Stable
        </div>
    </div>

    <!-- SECTION 1: Responsive Banner Slider -->
    <?php if(!empty($banners)): ?>
    <div class="relative w-full h-[180px] sm:h-[280px] md:h-[350px] lg:h-[420px] mb-10 rounded-3xl overflow-hidden group shadow-[0_20px_50px_rgba(0,0,0,0.5)] bg-slate-900 border border-[#00f0ff]/20" id="heroSliderContainer">
        
        <div class="flex overflow-x-auto snap-x-mandatory h-full no-scrollbar scroll-smooth" id="bannerSlider">
            <?php foreach($banners as $index => $b): ?>
                <div class="w-full flex-shrink-0 snap-center relative h-full banner-slide" data-index="<?php echo $index; ?>">
                    <a href="<?php echo $b['target_url'] ?: '#'; ?>" target="<?php echo $b['target_url'] ? '_blank' : '_self'; ?>" class="block w-full h-full cursor-pointer overflow-hidden">
                        <img src="<?php echo BASE_URL . $b['image_path']; ?>" class="w-full h-full object-cover transition duration-1000 group-hover:scale-105 group-hover:rotate-1" loading="lazy" alt="<?php echo htmlspecialchars($b['title']); ?>">
                        
                        <!-- Gradient Overlay -->
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-900/40 to-transparent opacity-90"></div>
                        <div class="absolute inset-0 bg-blue-900/10 mix-blend-color-burn"></div>
                        
                        <!-- Banner Text -->
                        <div class="absolute bottom-0 left-0 p-6 sm:p-10 w-full z-10">
                            <span class="text-[10px] text-[#00f0ff] font-bold uppercase tracking-widest mb-2 block drop-shadow-md">Featured Deployment</span>
                            <h3 class="text-white text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-black drop-shadow-[0_0_15px_rgba(0,240,255,0.5)] mb-3 transform transition-transform duration-500 translate-y-0 group-hover:-translate-y-2 tracking-tighter leading-none"><?php echo htmlspecialchars($b['title']); ?></h3>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Progress Bar Indicator -->
        <div class="absolute bottom-0 left-0 w-full h-1.5 bg-slate-800 z-20">
            <div id="slideProgress" class="h-full bg-gradient-to-r from-blue-600 via-[#00f0ff] to-white shadow-[0_0_10px_#00f0ff] w-0"></div>
        </div>

        <!-- Navigation Dots -->
        <div class="absolute bottom-6 right-6 flex gap-2.5 z-20" id="sliderDots">
            <?php foreach($banners as $i => $b): ?>
                <button class="w-2.5 h-2.5 rounded-full transition-all duration-300 slider-dot <?php echo $i === 0 ? 'bg-[#00f0ff] shadow-[0_0_10px_rgba(0,240,255,0.8)] w-8' : 'bg-white/30 hover:bg-white/60'; ?>" data-target="<?php echo $i; ?>"></button>
            <?php endforeach; ?>
        </div>
        
        <!-- Arrow Controls -->
        <button id="prevSlide" class="absolute left-4 top-1/2 -translate-y-1/2 w-12 h-12 rounded-2xl bg-slate-900/50 backdrop-blur-md border border-white/10 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-300 hover:bg-[#00f0ff] hover:text-slate-900 hover:scale-110 shadow-lg"><i class="fas fa-chevron-left"></i></button>
        <button id="nextSlide" class="absolute right-4 top-1/2 -translate-y-1/2 w-12 h-12 rounded-2xl bg-slate-900/50 backdrop-blur-md border border-white/10 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-300 hover:bg-[#00f0ff] hover:text-slate-900 hover:scale-110 shadow-lg"><i class="fas fa-chevron-right"></i></button>
    </div>
    <?php endif; ?>

    <!-- SECTION 1.5: System Telemetry (Social Proof Counters) -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-14 relative z-10">
        <div class="bg-slate-900/60 backdrop-blur border border-slate-700/50 rounded-2xl p-4 text-center group hover:border-[#00f0ff]/50 transition-colors shadow-inner">
            <i class="fas fa-server text-xl text-slate-500 group-hover:text-[#00f0ff] mb-2 transition-colors"></i>
            <div class="text-2xl font-black text-white tracking-tighter"><span class="telemetry-counter" data-target="99.9" data-suffix="%">0</span></div>
            <div class="text-[9px] text-slate-400 uppercase tracking-widest font-bold mt-1">Uptime</div>
        </div>
        <div class="bg-slate-900/60 backdrop-blur border border-slate-700/50 rounded-2xl p-4 text-center group hover:border-purple-500/50 transition-colors shadow-inner">
            <i class="fas fa-users text-xl text-slate-500 group-hover:text-purple-400 mb-2 transition-colors"></i>
            <div class="text-2xl font-black text-white tracking-tighter"><span class="telemetry-counter" data-target="<?php echo $display_users; ?>" data-suffix="+">0</span></div>
            <div class="text-[9px] text-slate-400 uppercase tracking-widest font-bold mt-1">Active Nodes</div>
        </div>
        <div class="bg-slate-900/60 backdrop-blur border border-slate-700/50 rounded-2xl p-4 text-center group hover:border-green-500/50 transition-colors shadow-inner">
            <i class="fas fa-box-open text-xl text-slate-500 group-hover:text-green-400 mb-2 transition-colors"></i>
            <div class="text-2xl font-black text-white tracking-tighter"><span class="telemetry-counter" data-target="<?php echo $display_orders; ?>" data-suffix="+">0</span></div>
            <div class="text-[9px] text-slate-400 uppercase tracking-widest font-bold mt-1">Deliveries</div>
        </div>
        <div class="bg-slate-900/60 backdrop-blur border border-slate-700/50 rounded-2xl p-4 text-center group hover:border-yellow-500/50 transition-colors shadow-inner">
            <i class="fas fa-bolt text-xl text-slate-500 group-hover:text-yellow-400 mb-2 transition-colors"></i>
            <div class="text-2xl font-black text-white tracking-tighter"><span class="telemetry-counter" data-target="24" data-suffix="/7">0</span></div>
            <div class="text-[9px] text-slate-400 uppercase tracking-widest font-bold mt-1">Auto System</div>
        </div>
    </div>

    <!-- SECTION 2: Interactive Category Slider -->
    <div class="mb-16 relative">
        <div class="flex items-end justify-between mb-6">
            <h2 class="text-2xl md:text-3xl font-black text-white tracking-tight flex items-center gap-3">
                <i class="fas fa-network-wired text-[#00f0ff]"></i> Sector Directory
            </h2>
            <div class="hidden sm:flex gap-2">
                <button id="catPrev" class="w-8 h-8 rounded-lg bg-slate-800 hover:bg-[#00f0ff] text-slate-400 hover:text-slate-900 transition flex items-center justify-center"><i class="fas fa-angle-left"></i></button>
                <button id="catNext" class="w-8 h-8 rounded-lg bg-slate-800 hover:bg-[#00f0ff] text-slate-400 hover:text-slate-900 transition flex items-center justify-center"><i class="fas fa-angle-right"></i></button>
            </div>
        </div>

        <!-- Slider Container -->
        <div class="relative group">
            <div id="categorySlider" class="flex overflow-x-auto snap-x-mandatory gap-4 sm:gap-6 pb-6 pt-2 px-2 -mx-2 no-scrollbar scroll-smooth">
                <?php foreach($categories as $cat): ?>
                    <a href="index.php?module=shop&page=category&id=<?php echo $cat['id']; ?>" class="snap-start shrink-0 w-[140px] sm:w-[180px] lg:w-[220px] glass-card rounded-3xl overflow-hidden group/cat relative flex flex-col justify-end aspect-[4/5] border border-slate-700/50 hover:border-[#00f0ff]/50">
                        
                        <!-- Dynamic Background -->
                        <?php if(!empty($cat['image_url'])): ?>
                            <img src="<?php echo BASE_URL . $cat['image_url']; ?>" alt="<?php echo htmlspecialchars($cat['name']); ?>" class="absolute inset-0 w-full h-full object-cover opacity-60 group-hover/cat:opacity-100 transition-all duration-700 group-hover/cat:scale-110">
                        <?php else: ?>
                            <!-- Circuit Pattern Fallback if no image -->
                            <div class="absolute inset-0 bg-slate-800 flex items-center justify-center group-hover/cat:scale-110 transition duration-700 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iMiIgY3k9IjIiIHI9IjEiIGZpbGw9InJnYmEoMCwgMjQwLCAyNTUsIDAuMikiLz48L3N2Zz4=')] opacity-50"></div>
                        <?php endif; ?>

                        <!-- Overlays -->
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-900/60 to-transparent opacity-90 group-hover/cat:opacity-70 transition-opacity"></div>
                        
                        <!-- Hover Glow -->
                        <div class="absolute -bottom-10 left-1/2 -translate-x-1/2 w-24 h-24 bg-[#00f0ff]/40 rounded-full blur-2xl opacity-0 group-hover/cat:opacity-100 transition-opacity duration-500 pointer-events-none"></div>

                        <!-- Content -->
                        <div class="relative z-10 p-5 text-center w-full transform transition-transform duration-300 group-hover/cat:-translate-y-2">
                            <span class="inline-block px-2 py-1 bg-slate-900/80 border border-slate-700 rounded text-[8px] font-bold text-slate-400 uppercase tracking-widest mb-2 backdrop-blur-md">
                                <?php echo htmlspecialchars($cat['type']); ?>
                            </span>
                            <h3 class="text-sm sm:text-base font-black text-white uppercase tracking-wider drop-shadow-lg leading-tight"><?php echo htmlspecialchars($cat['name']); ?></h3>
                            <!-- Hover Reveal Text -->
                            <div class="h-0 overflow-hidden group-hover/cat:h-auto group-hover/cat:mt-2 transition-all duration-300">
                                <p class="text-[10px] text-slate-300 line-clamp-2 leading-relaxed">Enter Sector &rarr;</p>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Horizontal Scroll Progress Line -->
            <div class="absolute -bottom-2 left-0 w-full h-1 bg-slate-800/50 rounded-full overflow-hidden backdrop-blur">
                <div id="catScrollProgress" class="h-full bg-gradient-to-r from-blue-600 to-[#00f0ff] rounded-full w-0 transition-all duration-150"></div>
            </div>
        </div>
    </div>

    <!-- SECTION 3: Hot Deals (Flash Sales) -->
    <?php if(!empty($flash_sales)): ?>
    <div class="mb-16">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-3">
            <div class="flex items-center gap-3">
                <span class="relative flex h-4 w-4">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-4 w-4 bg-red-500 shadow-[0_0_10px_rgba(239,68,68,0.8)]"></span>
                </span>
                <h2 class="text-2xl md:text-3xl font-black text-white tracking-tight">Flash Sales</h2>
            </div>
            <!-- Dynamic Countdown visual -->
            <div class="text-xs font-mono text-red-400 font-bold bg-red-900/20 px-3 py-1.5 rounded-lg border border-red-900/50 uppercase tracking-widest w-fit flex items-center gap-2">
                <i class="fas fa-stopwatch"></i> Ends Soon
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
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-16 relative">
        <!-- Connecting Line Background -->
        <div class="hidden md:block absolute top-1/2 left-10 right-10 h-0.5 bg-slate-800 -z-10 transform -translate-y-1/2">
            <div class="h-full bg-gradient-to-r from-green-500 via-purple-500 to-yellow-500 opacity-50 w-full animate-pulse"></div>
        </div>

        <!-- Feature 1 -->
        <div class="glass border border-slate-700/50 p-5 rounded-2xl flex flex-col items-center text-center gap-3 relative overflow-hidden group hover:border-green-500/50 transition">
            <div class="w-14 h-14 rounded-2xl bg-green-500/10 flex items-center justify-center text-green-400 text-2xl border border-green-500/20 shadow-inner group-hover:scale-110 transition duration-300 rotate-3"><i class="fas fa-bolt"></i></div>
            <div>
                <div class="text-sm font-black text-white tracking-widest uppercase mb-1">Instant Delivery</div>
                <div class="text-xs text-slate-400 font-medium">Automated 24/7 Matrix</div>
            </div>
        </div>

        <!-- Feature 2 -->
        <div class="glass border border-slate-700/50 p-5 rounded-2xl flex flex-col items-center text-center gap-3 relative overflow-hidden group hover:border-purple-500/50 transition">
            <div class="w-14 h-14 rounded-2xl bg-purple-500/10 flex items-center justify-center text-purple-400 text-2xl border border-purple-500/20 shadow-inner group-hover:scale-110 transition duration-300 -rotate-3"><i class="fas fa-shield-alt"></i></div>
            <div>
                <div class="text-sm font-black text-white tracking-widest uppercase mb-1">Official Warranty</div>
                <div class="text-xs text-slate-400 font-medium">Secure Encrypted Protocol</div>
            </div>
        </div>

        <!-- Feature 3 -->
        <div class="glass border border-slate-700/50 p-5 rounded-2xl flex flex-col items-center text-center gap-3 relative overflow-hidden group hover:border-yellow-500/50 transition">
            <div class="w-14 h-14 rounded-2xl bg-yellow-500/10 flex items-center justify-center text-yellow-400 text-2xl border border-yellow-500/20 shadow-inner group-hover:scale-110 transition duration-300 rotate-3"><i class="fas fa-wallet"></i></div>
            <div>
                <div class="text-sm font-black text-white tracking-widest uppercase mb-1">Local Payment</div>
                <div class="text-xs text-slate-400 font-medium">Kpay / Wave Pay Supported</div>
            </div>
        </div>
    </div>

    <!-- SECTION 5: Main Hub (Sidebar + Grid) -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8 mb-16">
        
        <!-- LEFT SIDEBAR -->
        <div class="lg:col-span-1 space-y-8">
            
            <!-- Trending -->
            <div class="glass border border-slate-700/50 rounded-3xl p-6 shadow-xl relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-orange-500/10 rounded-full blur-3xl pointer-events-none"></div>
                <h3 class="text-lg font-black text-white flex items-center gap-2 mb-6 uppercase tracking-wider relative z-10">
                    <i class="fas fa-fire text-orange-500 animate-pulse"></i> Trending
                </h3>
                <div class="space-y-5 relative z-10">
                    <?php foreach($best_sellers as $product): ?>
                        <?php 
                            $b_base = $product['sale_price'] ?: $product['price'];
                            $b_final = $b_base * ((100 - $discount) / 100);
                        ?>
                        <a href="index.php?module=shop&page=product&id=<?php echo $product['id']; ?>" class="flex items-center gap-4 group">
                            
                            <div class="w-14 h-14 rounded-xl bg-slate-900 flex items-center justify-center text-[#00f0ff] border border-slate-700 shrink-0 group-hover:border-[#00f0ff]/50 transition overflow-hidden shadow-inner">
                                <?php if(!empty($product['image_path'])): ?>
                                    <img src="<?php echo BASE_URL . $product['image_path']; ?>" class="w-full h-full object-cover">
                                <?php elseif(!empty($product['cat_image'])): ?>
                                    <img src="<?php echo BASE_URL . $product['cat_image']; ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <i class="fas fa-box text-xl opacity-50"></i>
                                <?php endif; ?>
                            </div>
                            
                            <div class="min-w-0 flex-1">
                                <h4 class="text-sm font-bold text-slate-200 truncate group-hover:text-[#00f0ff] transition"><?php echo htmlspecialchars($product['name']); ?></h4>
                                <div class="flex items-center gap-2 text-[10px] mt-1">
                                    <span class="text-green-400 font-mono font-bold bg-green-900/20 px-2 py-0.5 rounded border border-green-500/20"><?php echo format_price($b_final); ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Telegram Ad / Community -->
            <a href="https://t.me/bunpremiumstore" target="_blank" class="block bg-gradient-to-br from-blue-900 to-slate-900 rounded-3xl p-6 border border-blue-500/30 shadow-[0_10px_30px_rgba(0,0,0,0.5)] hover:shadow-[0_15px_40px_rgba(0,240,255,0.2)] transition duration-300 group relative overflow-hidden transform hover:-translate-y-1">
                <div class="absolute -right-10 -bottom-10 w-32 h-32 bg-[#00f0ff]/20 rounded-full blur-3xl transition"></div>
                <div class="relative z-10 flex flex-col items-center text-center">
                    <div class="w-16 h-16 bg-[#00f0ff]/10 rounded-full flex items-center justify-center text-[#00f0ff] mb-4 border border-[#00f0ff]/30 group-hover:scale-110 transition duration-300">
                        <i class="fab fa-telegram-plane text-3xl"></i>
                    </div>
                    <h4 class="text-white font-black text-lg tracking-tight mb-1">Join the Network</h4>
                    <p class="text-slate-400 text-xs font-medium mb-4">Daily drops, giveaways, and 24/7 priority support.</p>
                    <span class="bg-[#00f0ff] text-slate-900 px-5 py-2 rounded-lg text-xs font-black uppercase tracking-widest w-full">Connect</span>
                </div>
            </a>

        </div>

        <!-- RIGHT MAIN GRID: New Arrivals -->
        <div class="lg:col-span-3">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end mb-6 gap-2">
                <div>
                    <h2 class="text-2xl md:text-3xl font-black text-white mb-1 tracking-tight">New Deployments</h2>
                    <p class="text-slate-400 text-sm font-medium">Fresh stock injected into the matrix</p>
                </div>
                <a href="index.php?module=shop&page=search" class="text-[#00f0ff] hover:text-white text-sm font-bold flex items-center gap-2 transition uppercase tracking-wider bg-[#00f0ff]/10 px-4 py-2 rounded-lg border border-[#00f0ff]/20 hover:bg-[#00f0ff]/20">
                    View Matrix <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">
                <?php foreach($recent_products as $product): 
                    include __DIR__ . '/product_card.php'; 
                endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Trusted Payment Gateways Marquee -->
    <div class="border-t border-slate-800 pt-8 pb-4 relative overflow-hidden">
        <p class="text-center text-[10px] font-black text-slate-500 uppercase tracking-[0.3em] mb-6">Encrypted Payment Gateways Supported</p>
        <div class="w-full overflow-hidden relative">
            <div class="absolute inset-y-0 left-0 w-24 bg-gradient-to-r from-slate-950 to-transparent z-10 pointer-events-none"></div>
            <div class="absolute inset-y-0 right-0 w-24 bg-gradient-to-l from-slate-950 to-transparent z-10 pointer-events-none"></div>
            
            <div class="flex items-center gap-12 sm:gap-24 animate-marquee-slow opacity-60 hover:opacity-100 transition-opacity w-max">
                <!-- Repeated to ensure smooth infinite scroll -->
                <?php for($i=0; $i<3; $i++): ?>
                    <div class="flex items-center gap-3 text-slate-400 font-bold tracking-wider uppercase text-sm"><i class="fas fa-wallet text-[#00f0ff] text-2xl"></i> KBZPay</div>
                    <div class="flex items-center gap-3 text-slate-400 font-bold tracking-wider uppercase text-sm"><i class="fas fa-money-bill-wave text-yellow-400 text-2xl"></i> Wave Money</div>
                    <div class="flex items-center gap-3 text-slate-400 font-bold tracking-wider uppercase text-sm"><i class="fab fa-bitcoin text-orange-400 text-2xl"></i> Crypto (USDT)</div>
                    <div class="flex items-center gap-3 text-slate-400 font-bold tracking-wider uppercase text-sm"><i class="fas fa-qrcode text-blue-400 text-2xl"></i> Binance Pay</div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

</div>

<!-- Floating Live Purchase Toast (Gemini Social Proof Feature) -->
<div id="livePurchaseToast" class="fixed bottom-24 left-4 md:bottom-8 md:left-8 z-50 hidden max-w-xs">
    <div class="bg-slate-900/95 backdrop-blur-xl border border-[#00f0ff]/40 p-3 rounded-2xl shadow-[0_10px_40px_rgba(0,240,255,0.2)] flex items-center gap-4 relative overflow-hidden">
        <div class="absolute top-0 left-0 w-1 h-full bg-gradient-to-b from-[#00f0ff] to-blue-600"></div>
        <div class="w-10 h-10 bg-[#00f0ff]/10 rounded-xl flex items-center justify-center text-[#00f0ff] shrink-0 border border-[#00f0ff]/20">
            <i class="fas fa-shopping-bag"></i>
        </div>
        <div class="pr-4">
            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mb-0.5"><span id="toastUser" class="text-white">Someone</span> just bought</p>
            <p class="text-sm font-black text-[#00f0ff] leading-tight line-clamp-1" id="toastItem">A Product</p>
            <p class="text-[9px] text-slate-500 font-mono mt-1" id="toastTime">Just now</p>
        </div>
    </div>
</div>

<!-- SCRIPTS: Sliders & Popups -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    
    // --- 0. TELEMETRY COUNTER ANIMATION ---
    const counters = document.querySelectorAll('.telemetry-counter');
    const speed = 100; // Lower = faster
    
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
        
        // Start animation when element is in viewport
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
    const hTime = 5000;

    const updateHero = (index) => {
        hDots.forEach((dot, i) => {
            dot.className = i === index 
                ? 'w-8 h-2.5 rounded-full transition-all duration-300 slider-dot bg-[#00f0ff] shadow-[0_0_10px_rgba(0,240,255,0.8)]' 
                : 'w-2.5 h-2.5 rounded-full transition-all duration-300 slider-dot bg-white/30 hover:bg-white/60';
        });
        
        // Reset and trigger animation
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

    // --- 3. LIVE PURCHASE TOAST (SOCIAL PROOF) ---
    const mockData = <?php echo $live_purchases_json; ?>;
    const toast = document.getElementById('livePurchaseToast');
    
    if(mockData.length > 0 && toast) {
        let toastIndex = 0;
        
        const showToast = () => {
            const data = mockData[toastIndex];
            document.getElementById('toastUser').innerText = data.user;
            document.getElementById('toastItem').innerText = data.item;
            document.getElementById('toastTime').innerText = data.time;
            
            toast.classList.remove('hidden', 'toast-exit');
            toast.classList.add('toast-enter');
            
            setTimeout(() => {
                toast.classList.remove('toast-enter');
                toast.classList.add('toast-exit');
                setTimeout(() => toast.classList.add('hidden'), 500);
            }, 4000); // Show for 4 seconds
            
            toastIndex = (toastIndex + 1) % mockData.length;
        };

        // First toast after 5 seconds, then randomly every 15-25 seconds
        setTimeout(() => {
            showToast();
            setInterval(showToast, Math.floor(Math.random() * 10000) + 15000); 
        }, 5000);
    }
});
</script>