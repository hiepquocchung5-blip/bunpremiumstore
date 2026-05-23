<?php
// modules/home/index.php
// PRODUCTION READY v5.0 - Human-Friendly UI, Simple English & Reorganized Hub

// 1. Fetch Banners (Active Slides)
$stmt = $pdo->query("SELECT * FROM banners ORDER BY display_order ASC, id DESC LIMIT 5");
$banners = $stmt->fetchAll();

// 2. Fetch Categories
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

// 4. Fetch "Best Sellers"
$stmt = $pdo->query("
    SELECT p.*, c.name as cat_name, c.image_url as cat_image, COUNT(o.id) as sales_count
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    LEFT JOIN orders o ON p.id = o.product_id AND o.status = 'active'
    GROUP BY p.id
    ORDER BY sales_count DESC LIMIT 6
");
$best_sellers = $stmt->fetchAll();

// 5. Fetch "Recent Arrivals"
$stmt = $pdo->query("
    SELECT p.*, c.name as cat_name, c.image_url as cat_image
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    ORDER BY p.id DESC LIMIT 18
");
$recent_products = $stmt->fetchAll();

// 6. Fetch Recent User Activity (For "Recent Sales" at bottom)
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
<div class="w-full bg-slate-950 border-b border-blue-500/10 py-2 mb-6 relative">
    <div class="whitespace-nowrap animate-marquee text-[10px] sm:text-xs text-blue-400 font-mono tracking-widest uppercase font-bold">
        🚀 System Online • Instant Delivery Enabled • 24/7 Support Available • New Products Added Daily • Secure Payments Active
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-12">
    
    <!-- User Greeting -->
    <div class="mb-10 animate-fade-in-down">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div class="flex-1">
                <div class="flex items-center gap-3 mb-2">
                    <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest bg-slate-900/50 px-2.5 py-1 rounded-md border border-slate-800">Account Overview</span>
                    <div class="h-px bg-slate-800 flex-1 hidden md:block"></div>
                </div>
                <h2 class="text-3xl md:text-5xl font-black text-white tracking-tight leading-none">
                    Welcome back, <span class="text-blue-400"><?php echo htmlspecialchars($first_name); ?></span>
                </h2>
                <p class="text-slate-400 text-sm mt-3 font-medium">Your store dashboard is ready. View your products and orders below.</p>
            </div>

            <?php if(is_logged_in()): ?>
            <div class="flex items-center gap-4 bg-slate-900/50 border border-slate-700/50 p-2 rounded-2xl shadow-xl">
                <div class="px-4 py-2 border-r border-slate-800">
                    <p class="text-[9px] text-slate-500 font-black uppercase tracking-widest mb-0.5">Pending Orders</p>
                    <p class="text-lg font-mono font-black text-yellow-500"><?php echo str_pad($user_stats['active_orders'], 2, '0', STR_PAD_LEFT); ?></p>
                </div>
                <div class="px-4 py-2">
                    <p class="text-[9px] text-slate-500 font-black uppercase tracking-widest mb-0.5">Your Discount</p>
                    <p class="text-lg font-mono font-black text-green-400"><?php echo $discount; ?>%</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- NOTIFICATION PROMPT -->
    <?php if(is_logged_in() && !$is_subscribed): ?>
    <div class="mb-8 bg-blue-900/10 border border-blue-500/30 p-5 md:p-6 rounded-2xl flex flex-col sm:flex-row items-center justify-between gap-4 relative overflow-hidden">
        <div class="flex items-center gap-4 relative z-10 w-full sm:w-auto">
            <div class="w-12 h-12 bg-blue-500/10 rounded-full flex items-center justify-center text-blue-500 border border-blue-500/20 shrink-0">
                <i class="fas fa-bell text-xl animate-pulse"></i>
            </div>
            <div>
                <h3 class="font-black text-white text-base md:text-lg">Enable Updates</h3>
                <p class="text-xs text-slate-400">Turn on notifications to receive instant updates for your orders.</p>
            </div>
        </div>
        <button onclick="initializeManualUplink(this)" class="w-full sm:w-auto bg-blue-600 hover:bg-blue-500 text-white font-bold px-6 py-3 rounded-xl transition transform active:scale-95 text-xs uppercase tracking-widest">
            Enable Now
        </button>
    </div>
    <script>
        function initializeManualUplink(btn) {
            btn.innerHTML = 'Loading...';
            if (typeof window.registerServiceWorker === 'function') {
                window.registerServiceWorker(true).then(() => {
                    btn.closest('.rounded-2xl').remove();
                    alert("Notifications Enabled! You will now receive order updates.");
                }).catch(err => {
                    btn.innerHTML = 'Error';
                    alert("Please allow notifications in your browser settings and try again.");
                });
            }
        }
    </script>
    <?php endif; ?>

    <!-- SECTION 1: Banner Slider -->
    <?php if(!empty($banners)): ?>
    <div class="relative w-full aspect-[4/3] sm:aspect-video lg:max-h-[500px] mb-10 rounded-3xl overflow-hidden group shadow-2xl bg-slate-900 border border-slate-700/50" id="heroSliderContainer">
        <div class="flex overflow-x-auto snap-x-mandatory h-full no-scrollbar scroll-smooth" id="bannerSlider">
            <?php foreach($banners as $index => $b): ?>
                <div class="w-full flex-shrink-0 snap-center relative h-full">
                    <a href="<?php echo $b['target_url'] ?: '#'; ?>" class="block w-full h-full">
                        <img src="<?php echo BASE_URL . $b['image_path']; ?>" class="w-full h-full object-cover animate-pan-image" loading="lazy">
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-transparent to-transparent opacity-80"></div>
                        <div class="absolute bottom-0 left-0 p-6 sm:p-12 w-full">
                            <span class="text-[10px] text-blue-400 font-black uppercase tracking-widest mb-3 block">Featured Offer</span>
                            <h3 class="text-white text-3xl sm:text-5xl md:text-6xl font-black mb-4 leading-none"><?php echo htmlspecialchars($b['title']); ?></h3>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="absolute bottom-0 left-0 w-full h-1 bg-slate-800/80">
            <div id="slideProgress" class="h-full bg-blue-500 w-0"></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- SECTION 2: Categories -->
    <div class="mb-16 relative">
        <div class="flex items-end justify-between mb-6">
            <h2 class="text-2xl md:text-3xl font-black text-white tracking-tight">Categories</h2>
            <div class="hidden sm:flex gap-2">
                <button id="catPrev" class="w-10 h-10 rounded-xl bg-slate-800 hover:bg-blue-600 text-slate-400 hover:text-white transition flex items-center justify-center shadow-lg"><i class="fas fa-angle-left"></i></button>
                <button id="catNext" class="w-10 h-10 rounded-xl bg-slate-800 hover:bg-blue-600 text-slate-400 hover:text-white transition flex items-center justify-center shadow-lg"><i class="fas fa-angle-right"></i></button>
            </div>
        </div>

        <div class="relative">
            <div id="categorySlider" class="flex overflow-x-auto snap-x-mandatory gap-4 sm:gap-6 pb-6 no-scrollbar scroll-smooth">
                <?php foreach($categories as $cat): ?>
                    <a href="index.php?module=shop&page=category&id=<?php echo $cat['id']; ?>" class="snap-start shrink-0 w-[140px] sm:w-[180px] lg:w-[240px] glass-card rounded-3xl overflow-hidden group/cat relative flex flex-col justify-end aspect-[4/5] border border-slate-700/50 hover:border-blue-500/50">
                        <?php if(!empty($cat['image_url'])): ?>
                            <img src="<?php echo BASE_URL . $cat['image_url']; ?>" class="absolute inset-0 w-full h-full object-cover opacity-70 group-hover/cat:opacity-100 transition-all duration-700 group-hover/cat:scale-110">
                        <?php else: ?>
                            <div class="absolute inset-0 bg-slate-800"></div>
                        <?php endif; ?>
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-transparent to-transparent opacity-90"></div>
                        <div class="relative z-10 p-5 md:p-6 text-center w-full">
                            <span class="inline-block px-2 py-0.5 bg-slate-900 border border-slate-700 rounded text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2">
                                <?php echo htmlspecialchars($cat['type']); ?>
                            </span>
                            <h3 class="text-base sm:text-lg font-black text-white uppercase tracking-wide leading-tight"><?php echo htmlspecialchars($cat['name']); ?></h3>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="absolute -bottom-2 left-0 w-full h-1 bg-slate-800/50 rounded-full overflow-hidden">
                <div id="catScrollProgress" class="h-full bg-blue-500 w-0 transition-all duration-150"></div>
            </div>
        </div>
    </div>

    <!-- SECTION: Staff Picks -->
    <div class="mb-16">
        <div class="flex items-center gap-4 mb-8">
            <h2 class="text-2xl md:text-3xl font-black text-white tracking-tight">Staff Picks</h2>
            <div class="h-px bg-slate-800 flex-1"></div>
            <span class="text-[10px] font-bold text-blue-400 uppercase tracking-widest border border-blue-500/30 px-3 py-1 rounded-full">Recommended</span>
        </div>
        
        <div class="flex flex-col lg:flex-row gap-8">
            <?php if(!empty($best_sellers[0])): 
                $f = $best_sellers[0];
            ?>
            <div class="lg:w-2/3 relative group rounded-3xl overflow-hidden border border-slate-700/50 shadow-2xl bg-slate-900">
                <div class="aspect-[16/9] md:aspect-auto md:h-[400px] relative">
                    <img src="<?php echo BASE_URL . ($f['image_path'] ?: $f['cat_image']); ?>" class="w-full h-full object-cover transition-all duration-700 group-hover:scale-105">
                    <div class="absolute inset-0 bg-gradient-to-r from-slate-950 via-transparent to-transparent"></div>
                    <div class="absolute inset-0 p-8 md:p-12 flex flex-col justify-center max-w-lg">
                        <span class="text-xs font-black text-blue-400 uppercase tracking-widest mb-2">Editor's Choice</span>
                        <h3 class="text-3xl md:text-5xl font-black text-white mb-4 leading-none"><?php echo htmlspecialchars($f['name']); ?></h3>
                        <p class="text-slate-400 text-sm mb-8 line-clamp-2">Our most reliable choice with instant delivery and full warranty.</p>
                        <a href="index.php?module=shop&page=product&id=<?php echo $f['id']; ?>" class="w-fit px-8 py-4 bg-blue-600 hover:bg-blue-500 text-white font-black rounded-2xl transition-all uppercase tracking-widest text-xs shadow-lg shadow-blue-600/20">
                            Buy Now <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="lg:w-1/3 flex flex-col gap-6">
                <?php for($i=1; $i<min(3, count($best_sellers)); $i++): 
                    $p = $best_sellers[$i];
                ?>
                <a href="index.php?module=shop&page=product&id=<?php echo $p['id']; ?>" class="flex-1 bg-slate-900 border border-slate-700/50 rounded-3xl p-6 group hover:border-blue-500/50 transition-all relative overflow-hidden">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 rounded-2xl bg-slate-800 border border-slate-700 overflow-hidden shrink-0">
                            <img src="<?php echo BASE_URL . ($p['image_path'] ?: $p['cat_image']); ?>" class="w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-500">
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-white mb-1 group-hover:text-blue-400 transition-colors"><?php echo htmlspecialchars($p['name']); ?></h4>
                            <p class="text-[10px] text-slate-500 font-medium">Verified Product</p>
                        </div>
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
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php foreach($flash_sales as $product): 
                include __DIR__ . '/product_card.php'; 
            endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- SECTION 4: Features -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-16 mt-10">
        <div class="bg-slate-900/60 border border-slate-700/50 p-8 rounded-3xl flex flex-col items-center text-center gap-4">
            <div class="w-16 h-16 rounded-2xl bg-green-500/10 flex items-center justify-center text-green-500 text-3xl">
                <i class="fas fa-bolt"></i>
            </div>
            <div class="text-sm font-black text-white uppercase tracking-widest">Instant Delivery</div>
            <p class="text-xs text-slate-400">Products are sent to you right after payment.</p>
        </div>
        <div class="bg-slate-900/60 border border-slate-700/50 p-8 rounded-3xl flex flex-col items-center text-center gap-4">
            <div class="w-16 h-16 rounded-2xl bg-blue-500/10 flex items-center justify-center text-blue-500 text-3xl">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="text-sm font-black text-white uppercase tracking-widest">100% Warranty</div>
            <p class="text-xs text-slate-400">We provide a full warranty for all purchases.</p>
        </div>
        <div class="bg-slate-900/60 border border-slate-700/50 p-8 rounded-3xl flex flex-col items-center text-center gap-4">
            <div class="w-16 h-16 rounded-2xl bg-yellow-500/10 flex items-center justify-center text-yellow-500 text-3xl">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="text-sm font-black text-white uppercase tracking-widest">Easy Payments</div>
            <p class="text-xs text-slate-400">We accept KBZPay and WavePay instantly.</p>
        </div>
    </div>

    <!-- SECTION 5: Recent Arrivals -->
    <div class="mb-16">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end mb-8 gap-3">
            <div>
                <h2 class="text-3xl md:text-4xl font-black text-white tracking-tight">Recent Arrivals</h2>
                <p class="text-slate-400 text-sm font-medium">Check out our latest digital products</p>
            </div>
            <a href="index.php?module=shop&page=search" class="text-blue-400 hover:text-white text-sm font-black bg-blue-500/10 hover:bg-blue-600 px-5 py-3 rounded-xl border border-blue-500/20 uppercase tracking-widest transition-all">
                View All <i class="fas fa-arrow-right ml-2"></i>
            </a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach($recent_products as $product): 
                include __DIR__ . '/product_card.php'; 
            endforeach; ?>
        </div>
    </div>

    <!-- SECTION 6: Recent Sales (At Bottom) -->
    <div class="bg-slate-900/60 backdrop-blur-xl border border-slate-700/50 rounded-3xl p-6 md:p-8 shadow-2xl group mb-12">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-black text-white flex items-center gap-3 uppercase tracking-wider">
                <i class="fas fa-history text-blue-400"></i> Recent Sales
            </h3>
            <span class="text-[10px] text-green-400 font-mono font-bold bg-green-500/10 px-3 py-1 rounded-full border border-green-500/20">LIVE</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="activityFeed">
            <?php foreach($recent_activity as $act): 
                $u_safe = substr($act['username'], 0, 1) . '***' . substr($act['username'], -1);
            ?>
            <div class="flex items-center justify-between gap-4 p-4 bg-slate-800/40 rounded-2xl border border-slate-700/30">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-8 h-8 rounded-full bg-slate-900 flex items-center justify-center border border-slate-700 text-blue-400 shrink-0">
                        <i class="fas fa-user text-xs"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-slate-200 truncate">Customer <span class="text-blue-400">@<?php echo $u_safe; ?></span></p>
                        <p class="text-[10px] text-slate-500 truncate">Bought <b><?php echo htmlspecialchars($act['item_name']); ?></b></p>
                    </div>
                </div>
                <div class="text-right shrink-0">
                    <span class="text-[9px] text-slate-600 font-mono"><?php echo date('H:i', strtotime($act['created_at'])); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. HERO SLIDER
    const hSlider = document.getElementById('bannerSlider');
    const hDots = document.querySelectorAll('.slider-dot');
    const hProgress = document.getElementById('slideProgress');
    let hInterval, hTime = 6000;

    const updateHero = (idx) => {
        hDots.forEach((dot, i) => dot.className = i === idx ? 'w-8 h-2.5 rounded-full transition-all duration-300 slider-dot bg-white' : 'w-2.5 h-2.5 rounded-full transition-all duration-300 slider-dot bg-white/30 hover:bg-white/60');
        if(hProgress) { hProgress.style.animation = 'none'; hProgress.offsetHeight; hProgress.style.animation = `loadProgress ${hTime}ms linear forwards`; }
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

    if(hSlider) {
        hInterval = setInterval(() => moveHero(true), hTime);
        hDots.forEach((dot, idx) => dot.onclick = () => { clearInterval(hInterval); hSlider.scrollTo({ left: idx * hSlider.clientWidth, behavior: 'smooth' }); updateHero(idx); hInterval = setInterval(() => moveHero(true), hTime); });
        updateHero(0);
    }

    // 2. CATEGORY SLIDER
    const cSlider = document.getElementById('categorySlider');
    const cProgress = document.getElementById('catScrollProgress');
    if(cSlider && cProgress) {
        cSlider.onscroll = () => cProgress.style.width = (cSlider.scrollLeft / (cSlider.scrollWidth - cSlider.clientWidth) * 100) + '%';
        document.getElementById('catNext').onclick = () => cSlider.scrollBy({ left: 300, behavior: 'smooth' });
        document.getElementById('catPrev').onclick = () => cSlider.scrollBy({ left: -300, behavior: 'smooth' });
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