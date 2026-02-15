<?php
// modules/home/index.php
// PRODUCTION READY v1.0

// 1. Fetch Banners (Active Slides)
$stmt = $pdo->query("SELECT * FROM banners ORDER BY display_order ASC, id DESC LIMIT 5");
$banners = $stmt->fetchAll();

// 2. Fetch Categories
$stmt = $pdo->query("SELECT * FROM categories ORDER BY id ASC");
$categories = $stmt->fetchAll();

// 3. Fetch "Hot Deals" (Sale Items)
$stmt = $pdo->query("
    SELECT p.*, c.name as cat_name, c.icon_class 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    WHERE p.sale_price IS NOT NULL AND p.sale_price < p.price
    ORDER BY RAND() LIMIT 4
");
$flash_sales = $stmt->fetchAll();

// 4. Fetch "Best Sellers" (Based on active orders)
$stmt = $pdo->query("
    SELECT p.*, c.name as cat_name, c.icon_class, COUNT(o.id) as sales_count
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    LEFT JOIN orders o ON p.id = o.product_id AND o.status = 'active'
    GROUP BY p.id
    ORDER BY sales_count DESC LIMIT 5
");
$best_sellers = $stmt->fetchAll();

// 5. Fetch "Recent Arrivals" (Main Grid)
$stmt = $pdo->query("
    SELECT p.*, c.name as cat_name, c.icon_class 
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

// 7. Get Current User Discount
$discount = is_logged_in() ? get_user_discount($_SESSION['user_id']) : 0;
?>

<style>
    /* Custom Scroll & Animations */
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    
    .glass-card {
        background: rgba(31, 41, 55, 0.6);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.05);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .glass-card:hover {
        background: rgba(31, 41, 55, 0.8);
        border-color: rgba(59, 130, 246, 0.4);
        transform: translateY(-4px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
    }
    
    .animate-marquee { animation: marquee 20s linear infinite; }
    @keyframes marquee { 0% { transform: translateX(100%); } 100% { transform: translateX(-100%); } }
</style>

<!-- SECTION 0: News Ticker (Optional Polish) -->
<div class="w-full bg-gray-900 border-b border-gray-800 overflow-hidden py-1 mb-6">
    <div class="whitespace-nowrap animate-marquee text-xs text-gray-400 font-mono">
        🚀 Welcome to DigitalMarketplaceMM • Instant Delivery 24/7 • Official Game Keys & Premium Accounts • Join our Telegram for Giveaways! • Verified KBZPay/Wave Agents
    </div>
</div>

<!-- SECTION 1: Banner Slider -->
<?php if(!empty($banners)): ?>
<div class="relative w-full h-48 md:h-80 lg:h-96 mb-12 rounded-2xl overflow-hidden group shadow-2xl bg-gray-800 border border-gray-700/50">
    <div class="flex overflow-x-auto snap-x snap-mandatory h-full no-scrollbar scroll-smooth" id="bannerSlider">
        <?php foreach($banners as $b): ?>
            <div class="w-full flex-shrink-0 snap-center relative h-full">
                <a href="<?php echo $b['target_url'] ?: '#'; ?>" target="<?php echo $b['target_url'] ? '_blank' : '_self'; ?>" class="block w-full h-full cursor-pointer">
                    <img src="<?php echo BASE_URL . $b['image_path']; ?>" class="w-full h-full object-cover transition duration-1000 hover:scale-105" loading="lazy" alt="<?php echo htmlspecialchars($b['title']); ?>">
                    <div class="absolute inset-0 bg-gradient-to-t from-gray-900 via-transparent to-transparent opacity-80"></div>
                    <div class="absolute bottom-0 left-0 p-6 md:p-10 w-full z-10">
                        <h3 class="text-white text-2xl md:text-5xl font-bold drop-shadow-lg mb-2 transform transition translate-y-0 group-hover:-translate-y-2"><?php echo htmlspecialchars($b['title']); ?></h3>
                        <div class="h-1.5 w-24 bg-blue-500 rounded-full shadow-lg"></div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Navigation Dots (Simple) -->
    <div class="absolute bottom-4 right-4 flex gap-2 z-20">
        <?php foreach($banners as $i => $b): ?>
            <div class="w-2 h-2 rounded-full bg-white/50 backdrop-blur"></div>
        <?php endforeach; ?>
    </div>
</div>
<?php else: ?>
    <div class="relative w-full h-64 mb-10 rounded-2xl bg-gray-800 flex items-center justify-center border border-gray-700 shadow-xl">
        <div class="text-center">
            <h2 class="text-4xl font-bold text-white mb-2">Digital<span class="text-blue-500">MM</span></h2>
            <p class="text-gray-400">Premium Digital Store</p>
        </div>
    </div>
<?php endif; ?>


<!-- SECTION 2: Categories -->
<div class="mb-16">
    <h2 class="text-2xl font-bold text-white mb-6">Explore Categories</h2>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <?php foreach($categories as $cat): ?>
            <a href="index.php?module=shop&page=category&id=<?php echo $cat['id']; ?>" class="glass-card p-5 rounded-2xl text-center group block border border-gray-700 hover:border-blue-500/50 relative overflow-hidden">
                <div class="absolute inset-0 bg-blue-600/5 opacity-0 group-hover:opacity-100 transition"></div>
                <div class="w-12 h-12 mx-auto bg-gray-900 rounded-xl flex items-center justify-center mb-3 group-hover:scale-110 transition border border-gray-700 group-hover:border-blue-500 shadow-lg relative z-10">
                    <i class="fas <?php echo $cat['icon_class']; ?> text-xl text-gray-400 group-hover:text-blue-400 transition-colors"></i>
                </div>
                <h3 class="text-xs font-bold text-gray-300 group-hover:text-white transition-colors relative z-10 uppercase tracking-wide"><?php echo htmlspecialchars($cat['name']); ?></h3>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- SECTION 3: Hot Deals (Flash Sales) -->
<?php if(!empty($flash_sales)): ?>
<div class="mb-16">
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-2">
            <span class="relative flex h-3 w-3">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
            </span>
            <h2 class="text-2xl font-bold text-white">Flash Sales</h2>
        </div>
        <div class="text-xs font-mono text-red-400 bg-red-900/20 px-2 py-1 rounded border border-red-900/30">Limited Time Offer</div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <?php foreach($flash_sales as $product): 
            include __DIR__ . '/product_card.php'; 
        endforeach; ?>
    </div>
</div>
<?php endif; ?>


 <!-- SECTION 4: Feature Strip & Telegram Ad -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-16">
    
    <!-- Telegram Ad Card -->
    <a href="https://t.me/bunpremiumstore" target="_blank" class="md:col-span-1 bg-blue-600 rounded-xl p-4 flex items-center justify-between shadow-lg shadow-blue-900/20 hover:bg-blue-500 transition group relative overflow-hidden">
        <div class="relative z-10">
            <h4 class="text-white font-bold text-sm uppercase">Join Channel</h4>
            <p class="text-blue-100 text-xs mt-1">Updates & Promos</p>
        </div>
        <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center text-white relative z-10 group-hover:scale-110 transition">
            <i class="fab fa-telegram-plane text-xl"></i>
        </div>
        <div class="absolute -right-4 -bottom-4 w-20 h-20 bg-white/10 rounded-full blur-xl group-hover:bg-white/20 transition"></div>
    </a>

    <!-- Feature 1 -->
    <div class="glass-card p-4 rounded-xl flex items-center gap-4">
        <div class="w-10 h-10 rounded-lg bg-green-500/10 flex items-center justify-center text-green-400 text-lg border border-green-500/20"><i class="fas fa-bolt"></i></div>
        <div><div class="text-sm font-bold text-white">Instant Delivery</div><div class="text-xs text-gray-400">Auto-System 24/7</div></div>
    </div>

    <!-- Feature 2 -->
    <div class="glass-card p-4 rounded-xl flex items-center gap-4">
        <div class="w-10 h-10 rounded-lg bg-purple-500/10 flex items-center justify-center text-purple-400 text-lg border border-purple-500/20"><i class="fas fa-shield-alt"></i></div>
        <div><div class="text-sm font-bold text-white">Official Warranty</div><div class="text-xs text-gray-400">Full Support</div></div>
    </div>

    <!-- Feature 3 -->
    <div class="glass-card p-4 rounded-xl flex items-center gap-4">
        <div class="w-10 h-10 rounded-lg bg-yellow-500/10 flex items-center justify-center text-yellow-400 text-lg border border-yellow-500/20"><i class="fas fa-wallet"></i></div>
        <div><div class="text-sm font-bold text-white">Local Payment</div><div class="text-xs text-gray-400">Kpay / Wave</div></div>
    </div>
</div>


<!-- SECTION 5: Main Hub (Sidebar + Grid) -->
<div class="grid grid-cols-1 lg:grid-cols-4 gap-8 mb-16">
    
    <!-- LEFT SIDEBAR -->
    <div class="lg:col-span-1 space-y-8">
        
        <!-- Trending -->
        <div class="bg-gray-800/50 rounded-2xl p-5 border border-gray-700/50">
            <h3 class="text-lg font-bold text-white flex items-center gap-2 mb-4">
                <i class="fas fa-chart-line text-blue-500"></i> Trending
            </h3>
            <div class="space-y-4">
                <?php foreach($best_sellers as $product): ?>
                    <?php 
                        $b_base = $product['sale_price'] ?: $product['price'];
                        $b_final = $b_base * ((100 - $discount) / 100);
                    ?>
                    <a href="index.php?module=shop&page=product&id=<?php echo $product['id']; ?>" class="flex items-center gap-3 group">
                        <div class="w-12 h-12 rounded-lg bg-gray-900 flex items-center justify-center text-blue-500 border border-gray-700 shrink-0 group-hover:border-blue-500/50 transition">
                            <i class="fas <?php echo htmlspecialchars($product['icon_class'] ?? 'fa-cube'); ?>"></i>
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-xs font-bold text-gray-200 truncate group-hover:text-blue-400 transition"><?php echo htmlspecialchars($product['name']); ?></h4>
                            <div class="flex items-center gap-2 text-[10px] mt-0.5">
                                <span class="text-green-400 font-mono font-bold"><?php echo format_price($b_final); ?></span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Reviews -->
        <?php if(!empty($recent_reviews)): ?>
        <div class="bg-gray-800/50 rounded-2xl p-5 border border-gray-700/50">
            <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
                <i class="fas fa-comments text-yellow-500"></i> Feedback
            </h3>
            <div class="space-y-4">
                <?php foreach($recent_reviews as $r): ?>
                    <a href="index.php?module=shop&page=product&id=<?php echo $r['pid']; ?>" class="block p-3 rounded-xl bg-gray-900/50 border border-gray-700/50 hover:border-gray-600 transition">
                        <div class="flex justify-between items-start mb-1">
                            <span class="text-xs font-bold text-gray-300"><?php echo htmlspecialchars($r['username']); ?></span>
                            <div class="flex text-[8px] text-yellow-500">
                                <?php for($i=0; $i<$r['rating']; $i++) echo '<i class="fas fa-star"></i>'; ?>
                            </div>
                        </div>
                        <p class="text-[10px] text-gray-400 italic line-clamp-2">"<?php echo htmlspecialchars($r['comment']); ?>"</p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- RIGHT MAIN GRID: New Arrivals -->
    <div class="lg:col-span-3">
        <div class="flex justify-between items-end mb-6">
            <div>
                <h2 class="text-2xl font-bold text-white mb-1">New Arrivals</h2>
                <p class="text-gray-400 text-sm">Fresh stock added to the store</p>
            </div>
            <a href="index.php?module=shop&page=search" class="text-blue-400 hover:text-white text-sm font-bold flex items-center gap-1 transition">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach($recent_products as $product): 
                include __DIR__ . '/product_card.php'; 
            endforeach; ?>
        </div>
    </div>
</div>

<!-- Bottom CTA (Reseller) -->
<?php if($discount == 0): ?>
<div class="relative rounded-2xl overflow-hidden border border-yellow-500/30">
    <div class="absolute inset-0 bg-gradient-to-r from-yellow-900/90 to-gray-900 z-10"></div>
    <!-- <img src="..." class="absolute inset-0 w-full h-full object-cover opacity-30"> -->
    <div class="relative z-20 p-8 md:p-12 flex flex-col md:flex-row items-center justify-between gap-6 text-center md:text-left">
        <div>
            <h2 class="text-2xl md:text-3xl font-bold text-white mb-2">Start Your Business Today</h2>
            <p class="text-yellow-200/80 max-w-lg">Join our Reseller Program and get up to <span class="text-white font-bold">35% OFF</span> on all products. Instant activation.</p>
        </div>
        <a href="index.php?module=user&page=agent" class="bg-yellow-500 hover:bg-yellow-400 text-black font-bold px-8 py-3 rounded-full shadow-lg shadow-yellow-500/20 transform hover:-translate-y-1 transition">
            Become an Agent
        </a>
    </div>
</div>
<?php endif; ?>