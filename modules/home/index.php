<?php
// modules/home/index.php
// PRODUCTION READY v5.0 - Human-Friendly UI, Simple English & Reorganized Hub

// 1. Fetch Banners (Active Slides)
$banners = matrix_cache_get('home_banners_v2');
if ($banners === false) {
    $stmt = $pdo->query("SELECT * FROM banners ORDER BY display_order ASC, id DESC LIMIT 5");
    $banners = $stmt->fetchAll();
    matrix_cache_set('home_banners_v2', $banners, 300);
}

// 2. Fetch Categories
$categories = matrix_cache_get('home_categories_v2');
if ($categories === false) {
    $stmt = $pdo->query("SELECT id, name, image_url, description, type FROM categories ORDER BY id ASC");
    $categories = $stmt->fetchAll();
    matrix_cache_set('home_categories_v2', $categories, 600);
}

// 3. Fetch "Hot Deals" (Sale Items)
$flash_sales = matrix_cache_get('home_flash_sales_v2');
if ($flash_sales === false) {
    $stmt = $pdo->query("
        SELECT p.*, c.name as cat_name, c.image_url as cat_image
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        WHERE p.sale_price IS NOT NULL AND p.sale_price < p.price
        ORDER BY RAND() LIMIT 4
    ");
    $flash_sales = $stmt->fetchAll();
    matrix_cache_set('home_flash_sales_v2', $flash_sales, 120);
}

// 4. Fetch "Best Sellers"
$best_sellers = matrix_cache_get('home_best_sellers_v2');
if ($best_sellers === false) {
    $stmt = $pdo->query("
        SELECT p.*, c.name as cat_name, c.image_url as cat_image, COUNT(o.id) as sales_count
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        LEFT JOIN orders o ON p.id = o.product_id AND o.status = 'active'
        GROUP BY p.id
        ORDER BY sales_count DESC LIMIT 6
    ");
    $best_sellers = $stmt->fetchAll();
    matrix_cache_set('home_best_sellers_v2', $best_sellers, 180);
}

// 5. Fetch "Recent Arrivals"
$recent_products = matrix_cache_get('home_recent_products_v2');
if ($recent_products === false) {
    $stmt = $pdo->query("
        SELECT p.*, c.name as cat_name, c.image_url as cat_image
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        ORDER BY p.id DESC LIMIT 18
    ");
    $recent_products = $stmt->fetchAll();
    matrix_cache_set('home_recent_products_v2', $recent_products, 120);
}

// 6. Fetch Recent User Activity (For "Recent Sales" at bottom)
$recent_activity = matrix_cache_get('home_recent_activity_v2');
if ($recent_activity === false) {
    $stmt = $pdo->query("
        SELECT o.id, u.username, COALESCE(p.name, ps.name) as item_name, o.created_at
        FROM orders o 
        JOIN users u ON o.user_id = u.id
        LEFT JOIN products p ON o.product_id = p.id 
        LEFT JOIN passes ps ON o.pass_id = ps.id
        WHERE o.status = 'active'
        ORDER BY o.id DESC LIMIT 10
    ");
    $recent_activity = $stmt->fetchAll();
    matrix_cache_set('home_recent_activity_v2', $recent_activity, 60);
}

// 7. Get Current User Discount & Stats
$user_id = $_SESSION['user_id'] ?? 0;
$discount = is_logged_in() ? get_user_discount($user_id) : 0;
$first_name = is_logged_in() ? explode(' ', $_SESSION['user_name'])[0] : 'Customer';

$user_stats = ['active_orders' => 0];
if(is_logged_in()) {
    $user_stats['active_orders'] = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'pending'");
    $user_stats['active_orders']->execute([$user_id]);
    $user_stats['active_orders'] = $user_stats['active_orders']->fetchColumn() ?: 0;
}

// 8. Check Push Status
$is_subscribed = false;
if (is_logged_in()) {
    try {
        $sub_check = $pdo->prepare("SELECT id FROM push_subscriptions WHERE user_id = ?");
        $sub_check->execute([$user_id]);
        $is_subscribed = $sub_check->rowCount() > 0;
    } catch (Exception $e) {}
}
?>

<style>
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

    .home-page-shell {
        overflow-x: clip;
        position: relative;
    }

    .home-slider-track {
        overscroll-behavior-x: contain;
        -webkit-overflow-scrolling: touch;
        touch-action: pan-x;
        scrollbar-width: none;
    }

    .home-slider-track::-webkit-scrollbar {
        display: none;
    }
    
    .glass-card {
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(255, 255, 255, 0.05);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .glass-card:hover {
        background: rgba(15, 23, 42, 0.8);
        border-color: rgba(59, 130, 246, 0.3);
        transform: translateY(-4px);
    }
    
    .animate-marquee { animation: marquee 35s linear infinite; }
    @keyframes marquee { 0% { transform: translateX(100%); } 100% { transform: translateX(-100%); } }

    @keyframes loadProgress { 0% { width: 0%; } 100% { width: 100%; } }
    
    @keyframes panImage {
        0% { transform: scale(1.05); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1.05); }
    }
    .animate-pan-image { animation: panImage 20s ease-in-out infinite; }
</style>

<!-- Top News Ticker -->
<div class="w-full bg-slate-900 border-b border-white/5 py-2.5 mb-6 relative overflow-hidden">
    <div class="whitespace-nowrap animate-marquee text-[10px] text-blue-400 font-bold tracking-widest uppercase px-4 flex items-center gap-2">
        <svg
            xmlns="http://www.w3.org/2000/svg"
            class="w-4 h-4 text-blue-400"
            fill="currentColor"
            viewBox="0 0 24 24"
        >
            <path d="M12 2L14.9 8.6L22 9.3L16.8 13.9L18.4 21L12 17.2L5.6 21L7.2 13.9L2 9.3L9.1 8.6L12 2Z"/>
        </svg>

        Welcome to DigitalMM • Instant Delivery • 24/7 Support • New Items Added Daily • Secure Payments Active
    </div>
</div>

<div class="home-page-shell max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-16">
    
    <!-- User Greeting -->
    <div class="mb-12">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div class="flex-1">
                <div class="flex items-center gap-3 mb-4">
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest bg-slate-800/50 px-3 py-1 rounded-full border border-white/5">Personal Hub</span>
                </div>
                <h2 class="text-3xl md:text-5xl font-bold text-white tracking-tight leading-tight">
                    Hello, <span class="text-blue-500"><?php echo htmlspecialchars($first_name); ?></span>
                </h2>
                <p class="text-slate-400 text-sm mt-4 font-medium max-w-2xl leading-relaxed">Discover the best digital deals, handpicked just for you. Your next favorite game or tool is just a click away.</p>
            </div>

            <?php if(is_logged_in()): ?>
            <div class="flex items-center gap-4 bg-slate-800/30 border border-white/5 p-2 rounded-2xl shadow-xl">
                <div class="px-5 py-2 border-r border-white/5">
                    <p class="text-[9px] text-slate-500 font-bold uppercase tracking-widest mb-1">Orders</p>
                    <p class="text-lg font-bold text-white"><?php echo $user_stats['active_orders']; ?></p>
                </div>
                <div class="px-5 py-2">
                    <p class="text-[9px] text-slate-500 font-bold uppercase tracking-widest mb-1">Discount</p>
                    <p class="text-lg font-bold text-emerald-400"><?php echo $discount; ?>%</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- SECTION 1: Banner Slider -->
    <?php if(!empty($banners)): ?>
    <div class="relative w-full aspect-[16/9] lg:max-h-[500px] mb-16 rounded-[2rem] overflow-hidden group shadow-2xl bg-slate-900 border border-white/5" id="heroSliderContainer">
        <div class="flex h-full overflow-x-auto snap-x snap-mandatory no-scrollbar scroll-smooth home-slider-track" id="bannerSlider">
            <?php foreach($banners as $index => $b): ?>
                <div class="w-full h-full flex-shrink-0 snap-center relative overflow-hidden">
                    <a href="<?php echo $b['target_url'] ?: '#'; ?>" class="block w-full h-full">
                        <img src="<?php echo BASE_URL . $b['image_path']; ?>" class="w-full h-full object-cover transition-transform duration-[10s] group-hover:scale-110" loading="eager">
                        <div class="absolute inset-0 bg-gradient-to-t from-[#0b0f1a] via-transparent to-transparent opacity-80"></div>
                        
                        <div class="absolute inset-0 flex flex-col justify-end p-8 md:p-16">
                            <div class="max-w-2xl">
                                <span class="inline-block px-3 py-1 bg-blue-600 text-white text-[10px] font-bold uppercase tracking-widest rounded-lg mb-4">Featured</span>
                                <h3 class="text-white text-3xl md:text-6xl font-bold mb-6 leading-tight tracking-tight">
                                    <?php echo htmlspecialchars($b['title']); ?>
                                </h3>
                                <div class="flex items-center gap-4">
                                    <span class="px-8 py-3 bg-white text-black font-bold rounded-xl text-sm transition-transform hover:scale-105 active:scale-95 shadow-xl">Explore Deals</span>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Navigation -->
        <div class="absolute bottom-8 right-8 z-20 flex items-center gap-3">
            <?php foreach($banners as $index => $b): ?>
                <button class="slider-dot w-2 h-2 rounded-full bg-white/20 transition-all duration-300" data-index="<?php echo $index; ?>"></button>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- SECTION 2: Categories -->
    <div class="mb-20">
        <div class="flex items-end justify-between mb-8">
            <h2 class="text-2xl font-bold text-white tracking-tight">Browse Categories</h2>
            <div class="flex gap-2">
                <button id="catPrev" class="w-11 h-11 rounded-xl bg-slate-800 hover:bg-slate-700 text-white transition flex items-center justify-center border border-white/5"><i class="fas fa-chevron-left text-sm"></i></button>
                <button id="catNext" class="w-11 h-11 rounded-xl bg-slate-800 hover:bg-slate-700 text-white transition flex items-center justify-center border border-white/5"><i class="fas fa-chevron-right text-sm"></i></button>
            </div>
        </div>

        <div class="relative">
            <div id="categorySlider" class="flex overflow-x-auto gap-6 pb-6 no-scrollbar scroll-smooth">
                <?php foreach($categories as $cat): ?>
                    <a href="index.php?module=shop&page=category&id=<?php echo $cat['id']; ?>" class="shrink-0 w-44 md:w-56 bg-slate-800/50 rounded-[2rem] p-6 border border-white/5 hover:border-blue-500/30 hover:bg-slate-800 transition-all group">
                        <div class="w-16 h-16 bg-slate-900 rounded-2xl flex items-center justify-center mb-6 shadow-lg group-hover:scale-110 transition-transform overflow-hidden border border-white/5">
                            <?php if(!empty($cat['image_url'])): ?>
                                <img src="<?php echo BASE_URL . $cat['image_url']; ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <i class="fas fa-th-large text-blue-400"></i>
                            <?php endif; ?>
                        </div>
                        <h3 class="text-white font-bold text-lg mb-2 truncate"><?php echo htmlspecialchars($cat['name']); ?></h3>
                        <p class="text-slate-500 text-xs line-clamp-2"><?php echo htmlspecialchars($cat['description']); ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- SECTION: Staff Picks -->
    <div class="mb-20">
        <div class="flex items-center gap-4 mb-10">
            <h2 class="text-2xl font-bold text-white tracking-tight">Our Recommendations</h2>
            <div class="h-px bg-white/5 flex-1"></div>
        </div>
        
        <div class="flex flex-col lg:flex-row gap-8">
            <?php if(!empty($best_sellers[0])): 
                $f = $best_sellers[0];
            ?>
            <div class="lg:w-2/3 relative group rounded-[2.5rem] overflow-hidden border border-white/5 shadow-2xl bg-slate-900">
                <div class="aspect-[16/9] md:aspect-auto md:h-[450px] relative">
                    <img src="<?php echo BASE_URL . ($f['image_path'] ?: $f['cat_image']); ?>" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">
                    <div class="absolute inset-0 bg-gradient-to-r from-[#0b0f1a] via-transparent to-transparent"></div>
                    <div class="absolute inset-0 p-8 md:p-16 flex flex-col justify-end md:justify-center max-w-xl">
                        <span class="text-[10px] font-bold text-blue-400 uppercase tracking-[0.2em] mb-4">Top Rated Choice</span>
                        <h3 class="text-3xl md:text-5xl font-bold text-white mb-6 leading-tight"><?php echo htmlspecialchars($f['name']); ?></h3>
                        <p class="text-slate-400 text-sm mb-8 leading-relaxed line-clamp-2">Experience the best quality digital service with our most popular pick. Trusted by thousands of happy customers.</p>
                        <a href="index.php?module=shop&page=product&id=<?php echo $f['id']; ?>" class="w-fit px-10 py-4 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-2xl transition-all shadow-xl shadow-blue-500/20 active:scale-95">
                            View Product
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="lg:w-1/3 flex flex-col gap-6">
                <?php for($i=1; $i<min(3, count($best_sellers)); $i++): 
                    $p = $best_sellers[$i];
                ?>
                <a href="index.php?module=shop&page=product&id=<?php echo $p['id']; ?>" class="flex-1 bg-slate-800/30 border border-white/5 rounded-[2rem] p-6 group hover:border-blue-500/30 transition-all flex items-center gap-6">
                    <div class="w-20 h-20 rounded-2xl bg-slate-900 border border-white/5 overflow-hidden shrink-0 shadow-lg">
                        <img src="<?php echo BASE_URL . ($p['image_path'] ?: $p['cat_image']); ?>" class="w-full h-full object-cover opacity-80 group-hover:opacity-100 transition-opacity">
                    </div>
                    <div class="min-w-0">
                        <h4 class="text-white font-bold mb-1 truncate group-hover:text-blue-400 transition-colors"><?php echo htmlspecialchars($p['name']); ?></h4>
                        <p class="text-slate-500 text-xs font-medium">Customer Favorite</p>
                    </div>
                </a>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <!-- SECTION 3: Flash Sales -->
    <?php if(!empty($flash_sales)): ?>
    <div class="mb-16">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-3">
            <h2 class="text-2xl md:text-3xl font-black text-white tracking-tight">Flash Sales</h2>
            <div class="text-[10px] md:text-xs font-mono text-red-400 font-bold bg-red-900/10 px-4 py-2 rounded-xl border border-red-900/30 uppercase tracking-widest flex items-center gap-2">
                <i class="fas fa-stopwatch"></i> Limited Offers
            </div>
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
            <?php foreach($flash_sales as $product): 
                include __DIR__ . '/product_card.php'; 
            endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- SECTION 4: Features -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6 mb-16 mt-8 sm:mt-10">
        <div class="bg-slate-900/60 border border-slate-700/50 p-6 sm:p-8 rounded-3xl flex flex-col items-center text-center gap-4">
            <div class="w-16 h-16 rounded-2xl bg-green-500/10 flex items-center justify-center text-green-500 text-3xl">
                <i class="fas fa-bolt"></i>
            </div>
            <div class="text-sm font-black text-white uppercase tracking-widest">Instant Delivery</div>
            <p class="text-xs text-slate-400">Products are sent to you right after payment.</p>
        </div>
        <div class="bg-slate-900/60 border border-slate-700/50 p-6 sm:p-8 rounded-3xl flex flex-col items-center text-center gap-4">
            <div class="w-16 h-16 rounded-2xl bg-blue-500/10 flex items-center justify-center text-blue-500 text-3xl">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="text-sm font-black text-white uppercase tracking-widest">100% Warranty</div>
            <p class="text-xs text-slate-400">We provide a full warranty for all purchases.</p>
        </div>
        <div class="bg-slate-900/60 border border-slate-700/50 p-6 sm:p-8 rounded-3xl flex flex-col items-center text-center gap-4">
            <div class="w-16 h-16 rounded-2xl bg-yellow-500/10 flex items-center justify-center text-yellow-500 text-3xl">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="text-sm font-black text-white uppercase tracking-widest">Easy Payments</div>
            <p class="text-xs text-slate-400">We accept KBZPay and WavePay instantly.</p>
        </div>
    </div>

    <!-- SECTION 5: Recent Arrivals -->
    <div class="mb-20">
        <div class="flex items-end justify-between mb-10">
            <div>
                <h2 class="text-2xl font-bold text-white tracking-tight">New Arrivals</h2>
                <p class="text-slate-500 text-sm mt-2">Explore the latest additions to our store</p>
            </div>
            <a href="index.php?module=shop&page=search" class="text-blue-400 hover:text-white text-sm font-bold transition flex items-center gap-2">
                Browse All <i class="fas fa-arrow-right text-[10px]"></i>
            </a>
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
            <?php foreach($recent_products as $product): 
                include __DIR__ . '/product_card.php'; 
            endforeach; ?>
        </div>
    </div>

    <!-- SECTION 6: Recent Activity -->
    <div class="bg-slate-800/30 border border-white/5 rounded-[2.5rem] p-10">
        <div class="flex items-center justify-between mb-10">
            <h3 class="text-xl font-bold text-white flex items-center gap-4">
                <i class="fas fa-shopping-bag text-blue-500"></i> Recent Purchases
            </h3>
            <span class="flex items-center gap-2 text-emerald-400 text-[10px] font-bold uppercase tracking-widest bg-emerald-500/10 px-3 py-1 rounded-full border border-emerald-500/20">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Live
            </span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="activityFeed">
            <?php foreach($recent_activity as $act): 
                $u_safe = substr($act['username'], 0, 1) . '***' . substr($act['username'], -1);
            ?>
            <div class="flex items-center gap-5 p-5 bg-slate-900/40 rounded-2xl border border-white/5">
                <div class="w-10 h-10 rounded-full bg-slate-800 flex items-center justify-center text-slate-500 text-sm shrink-0">
                    <i class="fas fa-user"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-bold text-slate-200 truncate">Customer <span class="text-blue-400">@<?php echo $u_safe; ?></span></p>
                    <p class="text-[11px] text-slate-500 mt-0.5 truncate">Purchased <b><?php echo htmlspecialchars($act['item_name']); ?></b></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. HERO SLIDER (Matrix Core Edition)
    const hSlider = document.getElementById('bannerSlider');
    const hDots = document.querySelectorAll('.slider-dot');
    const hProgress = document.getElementById('slideProgress');
    let hInterval, hTime = 6000;
    let heroScrollTimer = null;

    const updateHero = (idx) => {
        if (!hDots.length) return;
        const safeIndex = Math.max(0, Math.min(idx, hDots.length - 1));
        hDots.forEach((dot, i) => {
            if (i === safeIndex) {
                dot.className = 'slider-dot w-6 h-1.5 rounded-full bg-blue-500 transition-all duration-500 shadow-[0_0_10px_#3b82f6]';
            } else {
                dot.className = 'slider-dot w-1.5 h-1.5 rounded-full bg-white/20 transition-all duration-500 hover:bg-white/40';
            }
        });
        if(hProgress) {
            hProgress.style.transition = 'none';
            hProgress.style.width = '0%';
            setTimeout(() => {
                hProgress.style.transition = `width ${hTime}ms linear`;
                hProgress.style.width = '100%';
            }, 50);
        }
    };

    const moveHero = (next = true) => {
        if(!hSlider) return;
        const w = Math.max(1, hSlider.clientWidth);
        let nextIdx = Math.round(hSlider.scrollLeft / w) + (next ? 1 : -1);
        
        if (nextIdx >= hDots.length) nextIdx = 0;
        if (nextIdx < 0) nextIdx = hDots.length - 1;

        hSlider.scrollTo({ left: nextIdx * w, behavior: 'smooth' });
        updateHero(nextIdx);
    };

    if(hSlider && hDots.length > 0) {
        hInterval = setInterval(() => moveHero(true), hTime);
        hDots.forEach((dot, idx) => {
            dot.onclick = () => {
                clearInterval(hInterval);
                hSlider.scrollTo({ left: idx * Math.max(1, hSlider.clientWidth), behavior: 'smooth' });
                updateHero(idx);
                hInterval = setInterval(() => moveHero(true), hTime);
            };
        });
        
        // Sync dots on manual scroll
        hSlider.addEventListener('scroll', () => {
            clearTimeout(heroScrollTimer);
            heroScrollTimer = setTimeout(() => {
                const idx = Math.round(hSlider.scrollLeft / Math.max(1, hSlider.clientWidth));
                updateHero(idx);
            }, 80);
        }, { passive: true });

        updateHero(0);
    }

    // 2. CATEGORY SLIDER
    const cSlider = document.getElementById('categorySlider');
    const cProgress = document.getElementById('catScrollProgress');
    if(cSlider && cProgress) {
        const updateCategoryProgress = () => {
            const max = cSlider.scrollWidth - cSlider.clientWidth;
            cProgress.style.width = max > 0 ? ((cSlider.scrollLeft / max) * 100) + '%' : '0%';
        };
        cSlider.addEventListener('scroll', updateCategoryProgress, { passive: true });
        updateCategoryProgress();

        const catNext = document.getElementById('catNext');
        const catPrev = document.getElementById('catPrev');
        if(catNext) catNext.onclick = () => cSlider.scrollBy({ left: Math.max(260, cSlider.clientWidth * 0.8), behavior: 'smooth' });
        if(catPrev) catPrev.onclick = () => cSlider.scrollBy({ left: -Math.max(260, cSlider.clientWidth * 0.8), behavior: 'smooth' });
    }

    // 3. ACTIVITY SIMULATOR
    const feed = document.getElementById('activityFeed');
    if(feed) {
        const items = [{u:'k***n', i:'Steam Card'}, {u:'m***a', i:'ChatGPT'}, {u:'o***7', i:'Netflix'}, {u:'z***1', i:'Spotify'}];
        setInterval(() => {
            if(Math.random() > 0.8) {
                const item = items[Math.floor(Math.random() * items.length)];
                const html = `<div class="flex items-center justify-between gap-4 p-4 bg-slate-800/40 rounded-2xl border border-blue-500/10 animate-fade-in-up">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 rounded-full bg-slate-900 flex items-center justify-center border border-slate-700 text-blue-400 shrink-0"><i class="fas fa-shopping-cart text-[10px]"></i></div>
                        <div class="min-w-0">
                            <p class="text-xs font-bold text-white truncate">Customer <span class="text-blue-400">@${item.u}</span></p>
                            <p class="text-[10px] text-slate-500 truncate">Bought <b>${item.i}</b></p>
                        </div>
                    </div>
                    <div class="text-right shrink-0"><span class="text-[9px] text-blue-500 font-mono">JUST NOW</span></div>
                </div>`;
                feed.insertAdjacentHTML('afterbegin', html);
                if(feed.children.length > 9) feed.lastElementChild.remove();
            }
        }, 15000);
    }
});
</script>
