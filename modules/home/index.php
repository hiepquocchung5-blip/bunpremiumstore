<?php
// modules/home/index.php

// 1. Fetch Banners
$stmt = $pdo->query("SELECT * FROM banners ORDER BY display_order ASC, id DESC LIMIT 5");
$banners = $stmt->fetchAll();

// 2. Fetch Categories
$stmt = $pdo->query("SELECT * FROM categories ORDER BY id ASC");
$categories = $stmt->fetchAll();

// 3. Fetch "Hot Deals" (Items on Sale)
$stmt = $pdo->query("
    SELECT p.*, c.name as cat_name, c.icon_class 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    WHERE p.sale_price IS NOT NULL AND p.sale_price < p.price
    ORDER BY RAND() LIMIT 4
");
$flash_sales = $stmt->fetchAll();

// 4. Fetch "Best Sellers" (Most active orders)
$stmt = $pdo->query("
    SELECT p.*, c.name as cat_name, c.icon_class, COUNT(o.id) as sales_count
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    LEFT JOIN orders o ON p.id = o.product_id AND o.status = 'active'
    GROUP BY p.id
    ORDER BY sales_count DESC LIMIT 4
");
$best_sellers = $stmt->fetchAll();

// 5. Fetch "Recent Arrivals"
$stmt = $pdo->query("
    SELECT p.*, c.name as cat_name, c.icon_class 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    ORDER BY p.id DESC LIMIT 8
");
$recent_products = $stmt->fetchAll();

// 6. Fetch Recent Reviews (Social Proof)
$stmt = $pdo->query("
    SELECT r.*, u.username, p.name as product_name
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    JOIN products p ON r.product_id = p.id
    WHERE r.rating >= 4
    ORDER BY r.created_at DESC LIMIT 3
");
$recent_reviews = $stmt->fetchAll();

// 7. Get Agent Discount
$discount = is_logged_in() ? get_user_discount($_SESSION['user_id']) : 0;
?>

<style>
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    .glass-card {
        background: rgba(31, 41, 55, 0.7);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }
    .glass-card:hover {
        transform: translateY(-5px);
        border-color: rgba(59, 130, 246, 0.5);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
    }
</style>

<!-- SECTION 1: Banner Slider -->
<?php if(!empty($banners)): ?>
<div class="relative w-full h-48 md:h-96 mb-12 rounded-2xl overflow-hidden group shadow-2xl bg-gray-800 border border-gray-700">
    <div class="flex overflow-x-auto snap-x snap-mandatory h-full no-scrollbar scroll-smooth" id="bannerSlider">
        <?php foreach($banners as $b): ?>
            <div class="w-full flex-shrink-0 snap-center relative h-full">
                <a href="<?php echo $b['target_url'] ?: '#'; ?>" target="<?php echo $b['target_url'] ? '_blank' : '_self'; ?>" class="block w-full h-full cursor-pointer">
                    <img src="<?php echo BASE_URL . $b['image_path']; ?>" class="w-full h-full object-cover" loading="lazy" alt="<?php echo htmlspecialchars($b['title']); ?>">
                    <div class="absolute inset-0 bg-gradient-to-t from-gray-900 via-transparent to-transparent opacity-90"></div>
                    <div class="absolute bottom-0 left-0 p-6 md:p-10 w-full">
                        <h3 class="text-white text-2xl md:text-5xl font-bold drop-shadow-lg mb-2"><?php echo htmlspecialchars($b['title']); ?></h3>
                        <div class="h-1.5 w-24 bg-blue-500 rounded-full"></div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Discount Badge -->
    <?php if($discount > 0): ?>
        <div class="absolute top-6 right-6 bg-yellow-500/90 backdrop-blur text-black font-bold px-4 py-2 rounded-full shadow-lg animate-pulse z-10 flex items-center gap-2 border border-yellow-400">
            <i class="fas fa-crown"></i> 
            <span><?php echo $discount; ?>% Reseller OFF</span>
        </div>
    <?php endif; ?>
</div>
<?php else: ?>
    <!-- Fallback Hero -->
    <div class="relative w-full h-64 mb-10 rounded-2xl overflow-hidden bg-gray-800 flex items-center justify-center border border-gray-700">
        <div class="text-center">
            <h2 class="text-4xl font-bold text-white mb-2">Welcome to Digital<span class="text-blue-500">MM</span></h2>
            <p class="text-gray-400">Your Premium Digital Marketplace</p>
        </div>
    </div>
<?php endif; ?>

<!-- SECTION 2: Service Features -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-16">
    <div class="glass-card p-4 rounded-xl flex items-center gap-4">
        <div class="w-12 h-12 rounded-full bg-green-500/20 flex items-center justify-center text-green-400 text-xl border border-green-500/20"><i class="fas fa-bolt"></i></div>
        <div><div class="text-sm font-bold text-white">Instant</div><div class="text-xs text-gray-400">Auto-Delivery</div></div>
    </div>
    <div class="glass-card p-4 rounded-xl flex items-center gap-4">
        <div class="w-12 h-12 rounded-full bg-blue-500/20 flex items-center justify-center text-blue-400 text-xl border border-blue-500/20"><i class="fas fa-shield-alt"></i></div>
        <div><div class="text-sm font-bold text-white">Secure</div><div class="text-xs text-gray-400">KBZPay / Wave</div></div>
    </div>
    <div class="glass-card p-4 rounded-xl flex items-center gap-4">
        <div class="w-12 h-12 rounded-full bg-purple-500/20 flex items-center justify-center text-purple-400 text-xl border border-purple-500/20"><i class="fas fa-headset"></i></div>
        <div><div class="text-sm font-bold text-white">24/7 Help</div><div class="text-xs text-gray-400">Live Support</div></div>
    </div>
    <div class="glass-card p-4 rounded-xl flex items-center gap-4">
        <div class="w-12 h-12 rounded-full bg-yellow-500/20 flex items-center justify-center text-yellow-400 text-xl border border-yellow-500/20"><i class="fas fa-hand-holding-usd"></i></div>
        <div><div class="text-sm font-bold text-white">Best Rate</div><div class="text-xs text-gray-400">Cheap Prices</div></div>
    </div>
</div>

<!-- SECTION 3: Hot Deals (Flash Sales) -->
<?php if(!empty($flash_sales)): ?>
<div class="mb-16">
    <div class="flex items-center gap-2 mb-6">
        <i class="fas fa-fire text-red-500 text-2xl animate-pulse"></i>
        <h2 class="text-2xl font-bold text-white">Hot Deals</h2>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <?php foreach($flash_sales as $product): include 'modules/home/product_card.php'; endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- SECTION 4: Category Browse -->
<div class="mb-16">
    <h2 class="text-2xl font-bold text-white mb-6">Browse by Category</h2>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <?php foreach($categories as $cat): ?>
            <a href="index.php?module=shop&page=category&id=<?php echo $cat['id']; ?>" class="glass-card p-4 rounded-xl text-center group block">
                <div class="w-12 h-12 mx-auto bg-gray-900 rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition border border-gray-700 group-hover:border-blue-500">
                    <i class="fas <?php echo $cat['icon_class']; ?> text-lg text-gray-400 group-hover:text-blue-500"></i>
                </div>
                <h3 class="text-sm font-bold text-gray-200 group-hover:text-white"><?php echo htmlspecialchars($cat['name']); ?></h3>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- SECTION 5: Best Sellers & New Arrivals -->
<div class="grid grid-cols-1 lg:grid-cols-4 gap-8 mb-16">
    
    <!-- Best Sellers (Sidebar style on Large screens) -->
    <div class="lg:col-span-1 space-y-6">
        <h3 class="text-xl font-bold text-white flex items-center gap-2">
            <i class="fas fa-chart-line text-blue-500"></i> Trending Now
        </h3>
        <div class="space-y-4">
            <?php foreach($best_sellers as $product): ?>
                <?php 
                    $price = $product['sale_price'] ?: $product['price'];
                    $final = $price * ((100 - $discount) / 100);
                ?>
                <a href="index.php?module=shop&page=product&id=<?php echo $product['id']; ?>" class="flex items-center gap-4 glass-card p-3 rounded-xl border-transparent hover:border-gray-600">
                    <div class="w-12 h-12 rounded-lg bg-gray-900 flex items-center justify-center text-blue-500 border border-gray-700">
                        <i class="fas <?php echo htmlspecialchars($product['icon_class'] ?? 'fa-cube'); ?>"></i>
                    </div>
                    <div class="min-w-0">
                        <h4 class="text-sm font-bold text-white truncate"><?php echo htmlspecialchars($product['name']); ?></h4>
                        <div class="flex items-center gap-2 text-xs">
                            <span class="text-green-400 font-mono"><?php echo format_price($final); ?></span>
                            <?php if($product['sale_price']): ?>
                                <span class="text-gray-500 line-through"><?php echo format_price($product['price']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        
        <!-- Reviews Widget -->
        <?php if(!empty($recent_reviews)): ?>
        <div class="mt-8">
            <h3 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
                <i class="fas fa-comments text-yellow-500"></i> Community
            </h3>
            <div class="space-y-4">
                <?php foreach($recent_reviews as $r): ?>
                    <div class="glass-card p-4 rounded-xl border-l-4 border-l-yellow-500">
                        <div class="flex justify-between items-start mb-1">
                            <span class="text-xs font-bold text-gray-300"><?php echo htmlspecialchars($r['username']); ?></span>
                            <div class="flex text-[10px] text-yellow-500">
                                <?php for($i=0; $i<$r['rating']; $i++) echo '<i class="fas fa-star"></i>'; ?>
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 italic">"<?php echo htmlspecialchars($r['comment']); ?>"</p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Recent Arrivals (Main Grid) -->
    <div class="lg:col-span-3">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-white">Just Arrived</h2>
            <a href="index.php?module=shop&page=search" class="text-blue-400 hover:text-white text-sm font-bold">View All &rarr;</a>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach($recent_products as $product): include 'modules/home/product_card.php'; endforeach; ?>
        </div>
    </div>
</div>
