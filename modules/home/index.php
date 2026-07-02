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
$flash_sales = matrix_cache_get('home_flash_sales_v3');
if ($flash_sales === false) {
    $stmt = $pdo->query("
        SELECT p.*, c.name as cat_name, c.image_url as cat_image,
               (SELECT COUNT(*) FROM product_keys WHERE product_id = p.id AND is_sold = 0 AND order_id IS NULL) as stock_count
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.sale_price IS NOT NULL AND p.sale_price < p.price AND " . product_active_condition('p') . "
        ORDER BY RAND() LIMIT 4
    ");
    $flash_sales = $stmt->fetchAll();
    matrix_cache_set('home_flash_sales_v3', $flash_sales, 120);
}

// 4. Fetch Best Sellers
$best_sellers = matrix_cache_get('home_best_sellers_v3');
if ($best_sellers === false) {
    $stmt = $pdo->query("
        SELECT p.*, c.name as cat_name, c.image_url as cat_image, COUNT(o.id) as sales_count,
               (SELECT COUNT(*) FROM product_keys WHERE product_id = p.id AND is_sold = 0 AND order_id IS NULL) as stock_count
        FROM products p
        JOIN categories c ON p.category_id = c.id
        LEFT JOIN orders o ON p.id = o.product_id AND o.status = 'active'
        WHERE " . product_active_condition('p') . "
        GROUP BY p.id
        ORDER BY sales_count DESC LIMIT 6
    ");
    $best_sellers = $stmt->fetchAll();
    matrix_cache_set('home_best_sellers_v3', $best_sellers, 180);
}

// 5. Fetch Recent Arrivals
$recent_products = matrix_cache_get('home_recent_products_v3');
if ($recent_products === false) {
    $stmt = $pdo->query("
        SELECT p.*, c.name as cat_name, c.image_url as cat_image,
               (SELECT COUNT(*) FROM product_keys WHERE product_id = p.id AND is_sold = 0 AND order_id IS NULL) as stock_count
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE " . product_active_condition('p') . "
        ORDER BY p.id DESC LIMIT 18
    ");
    $recent_products = $stmt->fetchAll();
    matrix_cache_set('home_recent_products_v3', $recent_products, 120);
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

<style>
/* ─── Page Shell ─────────────────────────────────────── */
.home-page-shell { overflow-x: clip; position: relative; }

/* ─── Ticker ─────────────────────────────────────────── */
.animate-marquee { animation: marquee 38s linear infinite; }
@keyframes marquee { 0% { transform: translateX(100vw); } 100% { transform: translateX(-100%); } }

/* ─── Hero Grid ──────────────────────────────────────── */
.hero-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.25rem;
    margin-bottom: 3.5rem;
}
@media (min-width: 1024px) {
    .hero-grid { grid-template-columns: minmax(0,1.9fr) minmax(0,1fr); }
}

/* ─── Banner Slider ──────────────────────────────────── */
.banner-wrap {
    position: relative;
    border-radius: 1.75rem;
    overflow: hidden;
    box-shadow: 0 24px 60px rgba(0,0,0,.35);
    background: #0b0f1a;
}
.banner-track { position: relative; height: 380px; }
@media (max-width: 640px) { .banner-track { height: 230px; } }

.banner-slide {
    position: absolute; inset: 0;
    opacity: 0;
    pointer-events: none;
    transition: opacity .9s cubic-bezier(.4,0,.2,1);
}
.banner-slide.active { opacity: 1; pointer-events: auto; }
.banner-slide img { width: 100%; height: 100%; object-fit: cover; display: block; }

.banner-overlay {
    position: absolute; inset: 0;
    background: linear-gradient(160deg, rgba(11,15,26,.15) 0%, rgba(11,15,26,.65) 100%);
    display: flex; flex-direction: column; justify-content: flex-end;
    padding: 2rem 2rem 2.5rem;
    z-index: 3;
}
.banner-title {
    font-size: clamp(1.25rem, 3vw, 2.25rem);
    font-weight: 900;
    color: #fff;
    line-height: 1.2;
    text-shadow: 0 3px 10px rgba(0,0,0,.5);
    margin-bottom: .85rem;
}
.banner-slide.active .banner-title { animation: slideInUp .55s ease-out .15s both; }

.banner-cta {
    display: inline-flex; align-items: center; gap: .5rem;
    padding: .6rem 1.25rem;
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: #fff; font-weight: 700; font-size: .8rem;
    border-radius: 10px; text-decoration: none;
    transition: transform .25s, box-shadow .25s;
    box-shadow: 0 4px 14px rgba(59,130,246,.4);
}
.banner-slide.active .banner-cta { animation: slideInUp .55s ease-out .3s both; }
.banner-cta:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(59,130,246,.55); }

.banner-arrow {
    position: absolute; top: 50%; transform: translateY(-50%);
    width: 38px; height: 38px;
    background: rgba(255,255,255,.13);
    border: 1px solid rgba(255,255,255,.28);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: #fff; cursor: pointer; z-index: 10;
    transition: background .25s, border-color .25s;
}
.banner-arrow:hover { background: rgba(255,255,255,.24); border-color: rgba(255,255,255,.5); }
.banner-prev { left: 1rem; }
.banner-next { right: 1rem; }

.banner-dots {
    position: absolute; bottom: 1.1rem; left: 50%; transform: translateX(-50%);
    display: flex; gap: .5rem; z-index: 10;
}
.banner-dot {
    width: 8px; height: 8px; border-radius: 4px;
    background: rgba(255,255,255,.35); border: 1.5px solid rgba(255,255,255,.5);
    cursor: pointer; transition: all .3s ease;
}
.banner-dot.active { width: 26px; background: #fff; border-color: #fff; }

@keyframes slideInUp {
    from { opacity: 0; transform: translateY(18px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ─── Category Panel ─────────────────────────────────── */
.cat-panel {
    display: flex; flex-direction: column;
    background: rgba(15,23,42,.55);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,.06);
    border-radius: 1.75rem;
    overflow: hidden;
    height: 380px;
}
@media (max-width: 1023px) {
    .cat-panel { height: auto; }
    .cat-grid-scroll { max-height: none; }
}
@media (max-width: 640px) {
    .cat-panel { background: transparent; border: none; border-radius: 0; }
}

.cat-panel-header {
    padding: 1.1rem 1.4rem .75rem;
    font-size: .65rem; font-weight: 800;
    text-transform: uppercase; letter-spacing: .18em;
    color: #64748b;
    border-bottom: 1px solid rgba(255,255,255,.05);
    display: flex; align-items: center; justify-content: space-between;
    flex-shrink: 0;
}

/* Desktop: vertical scrollable grid */
.cat-grid-scroll {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: .6rem;
    padding: .75rem;
    overflow-y: auto;
    flex: 1;
    scrollbar-width: none;
}
.cat-grid-scroll::-webkit-scrollbar { display: none; }

/* Mobile: horizontal scroll row */
@media (max-width: 1023px) {
    .cat-grid-scroll {
        display: flex;
        flex-direction: row;
        flex-wrap: nowrap;
        overflow-x: auto;
        overflow-y: visible;
        flex: unset;
        padding: .75rem .5rem;
        scroll-snap-type: x mandatory;
        gap: .6rem;
    }
}

.cat-card {
    position: relative;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: .5rem;
    padding: .85rem .5rem;
    border-radius: 1.1rem;
    border: 1px solid rgba(255,255,255,.05);
    background: rgba(15,23,42,.5);
    text-decoration: none;
    transition: transform .25s cubic-bezier(.4,0,.2,1), border-color .25s, background .25s, box-shadow .25s;
    text-align: center;
    overflow: hidden;
    cursor: pointer;
    flex-shrink: 0;
}
@media (max-width: 1023px) {
    .cat-card {
        min-width: 90px;
        scroll-snap-align: start;
        flex-direction: column;
    }
}
.cat-card::before {
    content: '';
    position: absolute; inset: 0;
    background: linear-gradient(135deg, rgba(59,130,246,.06), transparent);
    opacity: 0; transition: opacity .3s;
    border-radius: inherit;
}
.cat-card:hover { transform: translateY(-3px); border-color: rgba(59,130,246,.3); box-shadow: 0 8px 24px rgba(0,0,0,.3); }
.cat-card:hover::before { opacity: 1; }

.cat-img-ring {
    width: 48px; height: 48px;
    border-radius: 50%;
    border: 2px solid rgba(59,130,246,.2);
    overflow: hidden;
    background: rgba(15,23,42,.8);
    flex-shrink: 0;
    transition: border-color .25s, box-shadow .25s;
}
.cat-card:hover .cat-img-ring {
    border-color: rgba(59,130,246,.5);
    box-shadow: 0 0 0 4px rgba(59,130,246,.08);
}
.cat-img-ring img { width: 100%; height: 100%; object-fit: cover; }
.cat-img-ring .cat-icon { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; color: #475569; }

.cat-name {
    font-size: .67rem; font-weight: 700;
    color: #94a3b8; text-transform: uppercase;
    letter-spacing: .08em; line-height: 1.2;
    max-width: 80px; text-align: center;
    transition: color .2s;
}
.cat-card:hover .cat-name { color: #fff; }

/* ─── Section Headings ───────────────────────────────── */
.section-heading {
    display: flex; align-items: flex-end; justify-content: space-between;
    margin-bottom: 1.5rem;
}
.section-title { font-size: 1.5rem; font-weight: 900; color: #fff; letter-spacing: -.02em; }
.section-badge {
    font-size: .65rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: .15em; padding: .4rem .9rem;
    border-radius: .75rem;
}

/* ─── Product Card Image (size fix) ─────────────────── */
.prod-img-wrap {
    position: relative;
    width: 100%;
    aspect-ratio: 3/2;
    overflow: hidden;
    background: rgba(15,23,42,.9);
}
@media (max-width: 480px) {
    .prod-img-wrap { aspect-ratio: 4/3; }
}

/* ─── Feature Cards ──────────────────────────────────── */
.feature-card {
    background: rgba(15,23,42,.55);
    border: 1px solid rgba(255,255,255,.05);
    border-radius: 1.5rem;
    padding: 1.75rem;
    display: flex; flex-direction: column; align-items: center; text-align: center; gap: 1rem;
    transition: border-color .25s, transform .25s;
}
.feature-card:hover { border-color: rgba(59,130,246,.2); transform: translateY(-2px); }

/* ─── Activity Feed ──────────────────────────────────── */
@keyframes fadeSlideIn {
    from { opacity: 0; transform: translateY(-8px); }
    to   { opacity: 1; transform: translateY(0); }
}
.activity-new { animation: fadeSlideIn .4s ease-out; }
</style>

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
        </div>

    </div><!-- /hero-grid -->

    <!-- ══ FLASH SALES ══ -->
    <?php if (!empty($flash_sales)): ?>
    <div class="mb-16">
        <div class="section-heading">
            <div class="flex flex-col sm:flex-row sm:items-baseline gap-2 sm:gap-4">
                <div class="flex items-center gap-3">
                    <h2 class="section-title">Flash Sales</h2>
                    <span id="flash-sales-countdown" class="text-[11px] bg-red-500/10 border border-red-500/20 text-red-400 font-mono font-bold px-2.5 py-1 rounded-lg flex items-center gap-1.5 shadow-[0_0_15px_rgba(239,68,68,0.1)]">
                        <i class="fas fa-stopwatch text-[9px] animate-pulse"></i>
                        <span id="countdown-timer-val">00:00:00</span>
                    </span>
                </div>
                <p class="text-slate-500 text-xs mt-1">Limited-time offers — grab them fast</p>
            </div>
            <span class="section-badge text-red-400 bg-red-500/10 border border-red-500/20 hidden sm:inline-flex">
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

/* ── Flash Sales Live Countdown ───────────────── */
const initFlashSalesCountdown = () => {
    const timerElem = document.getElementById('countdown-timer-val');
    if (!timerElem) return;

    const updateTimer = () => {
        const now = new Date();
        const midnight = new Date();
        midnight.setHours(24, 0, 0, 0); // Next midnight
        
        const diff = midnight.getTime() - now.getTime();
        if (diff <= 0) {
            timerElem.textContent = '00:00:00';
            return;
        }
        
        const hours = Math.floor(diff / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);
        
        const hStr = String(hours).padStart(2, '0');
        const mStr = String(minutes).padStart(2, '0');
        const sStr = String(seconds).padStart(2, '0');
        
        timerElem.textContent = `${hStr}:${mStr}:${sStr}`;
    };

    updateTimer();
    setInterval(updateTimer, 1000);
};

// Orchestrate initialization
const runInitializers = () => {
    initBannerSlider();
    initActivityFeed();
    initFlashSalesCountdown();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', runInitializers);
} else {
    runInitializers();
}
</script>
