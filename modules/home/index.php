<?php
// modules/home/index.php

// 1. Fetch Banners
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

// 3. Fetch Flash Sales
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

// 4. Fetch Best Sellers
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

// 5. Fetch Recent Arrivals
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

// 6. Fetch Recent Activity
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

// 7. User Info
$user_id = $_SESSION['user_id'] ?? 0;
$discount = is_logged_in() ? get_user_discount($user_id) : 0;
$first_name = is_logged_in() ? explode(' ', $_SESSION['user_name'])[0] : 'Customer';

$user_stats = ['active_orders' => 0];
if (is_logged_in()) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'pending'");
    $s->execute([$user_id]);
    $user_stats['active_orders'] = $s->fetchColumn() ?: 0;
}

// 8. Push subscription check
$is_subscribed = false;
if (is_logged_in()) {
    try {
        $sub = $pdo->prepare("SELECT id FROM push_subscriptions WHERE user_id = ?");
        $sub->execute([$user_id]);
        $is_subscribed = $sub->rowCount() > 0;
    } catch (Exception $e) {}
}
?>



<!-- Top News Ticker -->
<div class="w-full bg-slate-900/80 border-b border-white/5 py-2.5 mb-8 overflow-hidden relative">
    <div class="whitespace-nowrap animate-marquee text-[10px] text-blue-400 font-bold tracking-widest uppercase flex items-center gap-3 px-4">
        <i class="fas fa-star text-blue-400 text-[8px]"></i>
        Welcome to DigitalMM &nbsp;•&nbsp; Instant Delivery &nbsp;•&nbsp; 24/7 Support &nbsp;•&nbsp; New Items Added Daily &nbsp;•&nbsp; Secure Payments Active &nbsp;•&nbsp; <i class="fas fa-star text-blue-400 text-[8px]"></i>
    </div>
</div>

<div class="home-page-shell max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-20">

    <!-- Greeting -->
    <div class="mb-10 flex flex-col sm:flex-row sm:items-end justify-between gap-5">
        <div>
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Personal Hub</p>
            <h2 class="text-3xl md:text-4xl font-black text-white tracking-tight leading-tight">
                Hello, <span class="text-blue-400"><?php echo htmlspecialchars($first_name); ?></span>
            </h2>
            <p class="text-slate-400 text-sm mt-2 max-w-lg leading-relaxed">Discover the best digital deals, handpicked just for you.</p>
        </div>
        <?php if (is_logged_in()): ?>
        <div class="flex items-center gap-1 bg-slate-800/40 border border-white/5 rounded-2xl overflow-hidden shadow-lg shrink-0">
            <div class="px-5 py-3 border-r border-white/5 text-center">
                <p class="text-[9px] text-slate-500 font-bold uppercase tracking-widest">Orders</p>
                <p class="text-xl font-black text-white mt-0.5"><?php echo $user_stats['active_orders']; ?></p>
            </div>
            <div class="px-5 py-3 text-center">
                <p class="text-[9px] text-slate-500 font-bold uppercase tracking-widest">Discount</p>
                <p class="text-xl font-black text-emerald-400 mt-0.5"><?php echo $discount; ?>%</p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ══ HERO LANDING: Banner + Categories ══ -->
    <div class="hero-grid">

        <!-- Banner Slider -->
        <div class="banner-wrap">
            <?php if (!empty($banners)): ?>
            <div class="banner-track" id="bannerTrack">
                <?php foreach ($banners as $i => $banner): ?>
                <div class="banner-slide <?php echo $i === 0 ? 'active' : ''; ?>" data-slide="<?php echo $i; ?>">
                    <img src="<?php echo BASE_URL . $banner['image_path']; ?>"
                         alt="<?php echo htmlspecialchars($banner['title']); ?>"
                         loading="<?php echo $i === 0 ? 'eager' : 'lazy'; ?>">
                    <div class="banner-overlay">
                        <div style="max-width:520px">
                            <h2 class="banner-title"><?php echo htmlspecialchars($banner['title']); ?></h2>
                            <?php if (!empty($banner['target_url'])): ?>
                            <a href="<?php echo $banner['target_url']; ?>" class="banner-cta">
                                Explore Now <i class="fas fa-arrow-right text-xs"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Arrows -->
            <button class="banner-prev banner-arrow" onclick="bannerSlide(-1)" aria-label="Previous">
                <i class="fas fa-chevron-left text-sm"></i>
            </button>
            <button class="banner-next banner-arrow" onclick="bannerSlide(1)" aria-label="Next">
                <i class="fas fa-chevron-right text-sm"></i>
            </button>

            <!-- Dots -->
            <div class="banner-dots">
                <?php foreach ($banners as $i => $banner): ?>
                <div class="banner-dot <?php echo $i === 0 ? 'active' : ''; ?>" onclick="bannerGoTo(<?php echo $i; ?>)"></div>
                <?php endforeach; ?>
            </div>

            <?php else: ?>
            <!-- Fallback when no banners -->
            <div class="banner-track flex items-center justify-center bg-gradient-to-br from-slate-900 to-slate-800">
                <div class="text-center px-8">
                    <div class="w-16 h-16 rounded-2xl bg-blue-600/20 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-store text-2xl text-blue-400"></i>
                    </div>
                    <h2 class="text-2xl font-black text-white mb-2">DigitalMM Store</h2>
                    <p class="text-slate-400 text-sm">Premium digital products, instant delivery.</p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Categories Panel -->
        <div class="cat-panel">
            <div class="cat-panel-header">
                <span><i class="fas fa-th-large mr-2 text-blue-500/70"></i>Shop by Category</span>
                <a href="index.php?module=shop&page=search" class="text-blue-500 hover:text-blue-400 text-[9px] font-bold tracking-widest transition">View All →</a>
            </div>

            <div class="cat-grid-scroll" id="categorySlider">
                <?php foreach ($categories as $cat):
                    $cat_url = 'index.php?module=shop&page=category&id=' . $cat['id'];
                ?>
                <a href="<?php echo $cat_url; ?>" class="cat-card">
                    <div class="cat-img-ring">
                        <?php if (!empty($cat['image_url'])): ?>
                            <img src="<?php echo BASE_URL . $cat['image_url']; ?>"
                                 alt="<?php echo htmlspecialchars($cat['name']); ?>"
                                 loading="lazy">
                        <?php else: ?>
                            <div class="cat-icon"><i class="fas fa-cube"></i></div>
                        <?php endif; ?>
                    </div>
                    <span class="cat-name"><?php echo htmlspecialchars($cat['name']); ?></span>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Category Scroll Progress Bar for Mobile/Tablet -->
            <div class="px-4 pb-3 lg:hidden">
                <div class="scroll-progress-bar">
                    <div class="scroll-progress-bar-fill" id="catScrollProgress"></div>
                </div>
            </div>
        </div>

    </div><!-- /hero-grid -->

    <!-- ══ FLASH SALES ══ -->
    <?php if (!empty($flash_sales)): ?>
    <div class="mb-16">
        <div class="section-heading">
            <div>
                <h2 class="section-title">Flash Sales</h2>
                <p class="text-slate-500 text-xs mt-1">Limited-time offers — grab them fast</p>
            </div>
            <span class="section-badge text-red-400 bg-red-500/10 border border-red-500/20">
                <i class="fas fa-stopwatch mr-1"></i>Limited
            </span>
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-5">
            <?php foreach ($flash_sales as $product):
                include __DIR__ . '/product_card.php';
            endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ══ FEATURES ══ -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-16">
        <div class="feature-card">
            <div class="w-14 h-14 rounded-2xl bg-emerald-500/10 flex items-center justify-center text-emerald-400 text-2xl">
                <i class="fas fa-bolt"></i>
            </div>
            <div>
                <p class="text-sm font-black text-white uppercase tracking-widest mb-1">Instant Delivery</p>
                <p class="text-xs text-slate-500 leading-relaxed">Products sent right after payment confirmation.</p>
            </div>
        </div>
        <div class="feature-card">
            <div class="w-14 h-14 rounded-2xl bg-blue-500/10 flex items-center justify-center text-blue-400 text-2xl">
                <i class="fas fa-shield-halved"></i>
            </div>
            <div>
                <p class="text-sm font-black text-white uppercase tracking-widest mb-1">100% Warranty</p>
                <p class="text-xs text-slate-500 leading-relaxed">Full warranty guaranteed on every purchase.</p>
            </div>
        </div>
        <div class="feature-card">
            <div class="w-14 h-14 rounded-2xl bg-amber-500/10 flex items-center justify-center text-amber-400 text-2xl">
                <i class="fas fa-wallet"></i>
            </div>
            <div>
                <p class="text-sm font-black text-white uppercase tracking-widest mb-1">Easy Payments</p>
                <p class="text-xs text-slate-500 leading-relaxed">KBZPay & WavePay accepted instantly.</p>
            </div>
        </div>
    </div>

    <!-- ══ NEW ARRIVALS ══ -->
    <div class="mb-20">
        <div class="section-heading mb-10">
            <div>
                <h2 class="section-title">New Arrivals</h2>
                <p class="text-slate-500 text-xs mt-1">Latest additions to our catalog</p>
            </div>
            <a href="index.php?module=shop&page=search" class="text-blue-400 hover:text-white text-xs font-bold transition flex items-center gap-1.5">
                Browse All <i class="fas fa-arrow-right text-[10px]"></i>
            </a>
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-5">
            <?php foreach ($recent_products as $product):
                include __DIR__ . '/product_card.php';
            endforeach; ?>
        </div>
    </div>

    <!-- ══ RECENT PURCHASES ══ -->
    <div class="bg-slate-800/25 border border-white/5 rounded-[2rem] p-6 sm:p-10">
        <div class="flex items-center justify-between mb-8">
            <h3 class="text-lg font-black text-white flex items-center gap-3">
                <i class="fas fa-shopping-bag text-blue-500"></i> Recent Purchases
            </h3>
            <span class="flex items-center gap-2 text-emerald-400 text-[9px] font-bold uppercase tracking-widest bg-emerald-500/10 px-3 py-1.5 rounded-full border border-emerald-500/20">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Live
            </span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="activityFeed">
            <?php foreach ($recent_activity as $act):
                $u_safe = substr($act['username'], 0, 1) . '***' . substr($act['username'], -1);
            ?>
            <div class="flex items-center gap-4 p-4 bg-slate-900/40 rounded-2xl border border-white/5">
                <div class="w-9 h-9 rounded-full bg-slate-800 flex items-center justify-center text-slate-500 text-xs shrink-0">
                    <i class="fas fa-user"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-bold text-slate-200 truncate">
                        Customer <span class="text-blue-400">@<?php echo $u_safe; ?></span>
                    </p>
                    <p class="text-[11px] text-slate-500 mt-0.5 truncate">
                        Purchased <b><?php echo htmlspecialchars($act['item_name']); ?></b>
                    </p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div><!-- /home-page-shell -->

<script>
// Define global hooks immediately so arrow clicks/dots do not ReferenceError
window.bannerSlide = window.bannerSlide || (() => {});
window.bannerGoTo = window.bannerGoTo || (() => {});

/* ── Banner Slider ─────────────────────────────── */
const initBannerSlider = () => {
    let current = 0;
    const slides = document.querySelectorAll('.banner-slide');
    const dots   = document.querySelectorAll('.banner-dot');
    let timer;

    if (!slides.length) return;

    function show(n) {
        if (n >= slides.length) n = 0;
        if (n < 0) n = slides.length - 1;
        current = n;
        slides.forEach((s, i) => s.classList.toggle('active', i === current));
        dots.forEach((d, i)   => d.classList.toggle('active', i === current));
        clearInterval(timer);
        timer = setInterval(() => show(current + 1), 6000);
    }

    window.bannerSlide  = (n) => show(current + n);
    window.bannerGoTo   = (n) => show(n);

    // Swipe support
    const track = document.getElementById('bannerTrack');
    if (track) {
        let sx = 0;
        track.addEventListener('touchstart', e => { sx = e.touches[0].clientX; }, { passive: true });
        track.addEventListener('touchend',   e => {
            const dx = e.changedTouches[0].clientX - sx;
            if (Math.abs(dx) > 40) show(current + (dx < 0 ? 1 : -1));
        }, { passive: true });
    }

    show(0);
};

/* ── Category Slider Progress ──────────────────── */
const initCategoryProgress = () => {
    const cSlider = document.getElementById('categorySlider');
    const cProgress = document.getElementById('catScrollProgress');
    if (cSlider && cProgress) {
        const updateCategoryProgress = () => {
            const max = cSlider.scrollWidth - cSlider.clientWidth;
            cProgress.style.width = max > 0 ? ((cSlider.scrollLeft / max) * 100) + '%' : '0%';
        };
        cSlider.addEventListener('scroll', updateCategoryProgress, { passive: true });
        updateCategoryProgress();
        if (window.ResizeObserver) {
            new ResizeObserver(updateCategoryProgress).observe(cSlider);
        }
    }
};

/* ── Activity Feed Live Ticker ─────────────────── */
const initActivityFeed = () => {
    const feed  = document.getElementById('activityFeed');
    const items = [
        {u:'k***n', i:'Steam Wallet Card'},
        {u:'m***a', i:'ChatGPT Plus'},
        {u:'o***7', i:'Netflix Premium'},
        {u:'z***1', i:'Spotify Premium'},
        {u:'t***y', i:'YouTube Premium'},
    ];

    if (!feed) return;
    setInterval(() => {
        if (Math.random() > 0.75) {
            const it = items[Math.floor(Math.random() * items.length)];
            const el = document.createElement('div');
            el.className = 'activity-new flex items-center gap-4 p-4 bg-slate-900/40 rounded-2xl border border-blue-500/10';
            el.innerHTML = `
                <div class="w-9 h-9 rounded-full bg-slate-800 flex items-center justify-center text-blue-400 text-xs shrink-0">
                     <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-bold text-slate-200 truncate">Customer <span class="text-blue-400">@${it.u}</span></p>
                    <p class="text-[11px] text-slate-500 mt-0.5 truncate">Purchased <b>${it.i}</b></p>
                </div>
                <div class="ml-auto shrink-0"><span class="text-[9px] text-blue-500 font-mono tracking-widest">JUST NOW</span></div>`;
            feed.prepend(el);
            if (feed.children.length > 9) feed.lastElementChild.remove();
        }
    }, 14000);
};

// Orchestrate initialization
const runInitializers = () => {
    initBannerSlider();
    initCategoryProgress();
    initActivityFeed();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', runInitializers);
} else {
    runInitializers();
}
</script>
