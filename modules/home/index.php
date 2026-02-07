<?php
// modules/home/index.php

// 1. Fetch Banners
$stmt = $pdo->query("SELECT * FROM banners ORDER BY display_order ASC, id DESC LIMIT 5");
$banners = $stmt->fetchAll();

// 2. Fetch Categories
$stmt = $pdo->query("SELECT * FROM categories ORDER BY id ASC");
$categories = $stmt->fetchAll();

// 3. Fetch Recent Products (New Arrivals)
$stmt = $pdo->query("
    SELECT p.*, c.name as cat_name, c.icon_class 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    ORDER BY p.id DESC LIMIT 6
");
$recent_products = $stmt->fetchAll();

// 4. Get Agent Discount
$discount = is_logged_in() ? get_user_discount($_SESSION['user_id']) : 0;
?>

<style>
    /* Hide scrollbar for Chrome, Safari and Opera */
    .no-scrollbar::-webkit-scrollbar { display: none; }
    /* Hide scrollbar for IE, Edge and Firefox */
    .no-scrollbar { -ms-overflow-style: none;  scrollbar-width: none; }
    
    .glass-card {
        background: rgba(31, 41, 55, 0.7);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }
</style>

<!-- SECTION 1: Dynamic Banner Swipe -->
<?php if(!empty($banners)): ?>
<div class="relative w-full h-56 md:h-96 mb-12 rounded-2xl overflow-hidden group shadow-2xl bg-gray-800 border border-gray-700">
    <div class="flex overflow-x-auto snap-x snap-mandatory h-full no-scrollbar scroll-smooth" id="bannerSlider">
        <?php foreach($banners as $b): ?>
            <div class="w-full flex-shrink-0 snap-center relative h-full">
                <a href="<?php echo $b['target_url'] ?: '#'; ?>" target="<?php echo $b['target_url'] ? '_blank' : '_self'; ?>" class="block w-full h-full cursor-pointer">
                    <img src="<?php echo $b['image_path']; ?>" class="w-full h-full object-cover transition duration-700 hover:scale-105" loading="lazy">
                    <!-- Gradient Overlay -->
                    <div class="absolute inset-0 bg-gradient-to-t from-gray-900 via-transparent to-transparent opacity-90"></div>
                    <!-- Caption -->
                    <div class="absolute bottom-0 left-0 p-6 md:p-10 w-full">
                        <h3 class="text-white text-2xl md:text-4xl font-bold drop-shadow-lg mb-2 transform transition translate-y-0 group-hover:-translate-y-1"><?php echo htmlspecialchars($b['title']); ?></h3>
                        <div class="h-1 w-20 bg-blue-500 rounded-full"></div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Agent Badge -->
    <?php if($discount > 0): ?>
        <div class="absolute top-6 right-6 bg-yellow-500/90 backdrop-blur text-black font-bold px-4 py-2 rounded-full shadow-lg animate-pulse z-10 flex items-center gap-2">
            <i class="fas fa-crown"></i> 
            <span><?php echo $discount; ?>% Reseller OFF</span>
        </div>
    <?php endif; ?>
</div>
<?php else: ?>
    <!-- Fallback Banner -->
    <div class="relative w-full h-64 mb-10 rounded-2xl overflow-hidden bg-gray-800 flex items-center justify-center border border-gray-700">
        <div class="text-center">
            <h2 class="text-3xl font-bold text-gray-300">Welcome to ScottSub</h2>
            <p class="text-gray-500 mt-2">Premium Digital Products</p>
        </div>
    </div>
<?php endif; ?>

<!-- SECTION 2: Service Features -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-16">
    <div class="glass-card p-4 rounded-xl flex items-center gap-4 hover:bg-gray-800 transition transform hover:-translate-y-1">
        <div class="w-12 h-12 rounded-full bg-green-500/20 flex items-center justify-center text-green-400 text-xl">
            <i class="fas fa-bolt"></i>
        </div>
        <div>
            <div class="text-sm font-bold text-white">Instant Delivery</div>
            <div class="text-xs text-gray-400">Automated System</div>
        </div>
    </div>
    <div class="glass-card p-4 rounded-xl flex items-center gap-4 hover:bg-gray-800 transition transform hover:-translate-y-1">
        <div class="w-12 h-12 rounded-full bg-blue-500/20 flex items-center justify-center text-blue-400 text-xl">
            <i class="fas fa-shield-alt"></i>
        </div>
        <div>
            <div class="text-sm font-bold text-white">Secure Pay</div>
            <div class="text-xs text-gray-400">KBZPay / Wave</div>
        </div>
    </div>
    <div class="glass-card p-4 rounded-xl flex items-center gap-4 hover:bg-gray-800 transition transform hover:-translate-y-1">
        <div class="w-12 h-12 rounded-full bg-purple-500/20 flex items-center justify-center text-purple-400 text-xl">
            <i class="fas fa-headset"></i>
        </div>
        <div>
            <div class="text-sm font-bold text-white">24/7 Support</div>
            <div class="text-xs text-gray-400">Live Chat</div>
        </div>
    </div>
    <div class="glass-card p-4 rounded-xl flex items-center gap-4 hover:bg-gray-800 transition transform hover:-translate-y-1">
        <div class="w-12 h-12 rounded-full bg-yellow-500/20 flex items-center justify-center text-yellow-400 text-xl">
            <i class="fas fa-star"></i>
        </div>
        <div>
            <div class="text-sm font-bold text-white">Best Prices</div>
            <div class="text-xs text-gray-400">Agent Discounts</div>
        </div>
    </div>
</div>

<!-- SECTION 3: Recent Arrivals -->
<div class="mb-16">
    <div class="flex justify-between items-end mb-6">
        <div>
            <h2 class="text-2xl font-bold text-white mb-1">Recent Arrivals</h2>
            <p class="text-gray-400 text-sm">Fresh stock added to the store</p>
        </div>
        <a href="index.php?module=shop&page=search" class="text-blue-400 text-sm hover:text-blue-300 font-medium flex items-center gap-1">
            View All <i class="fas fa-arrow-right"></i>
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach($recent_products as $product): ?>
            <?php 
                $final_price = $product['price'] * ((100 - $discount) / 100);
            ?>
            <div class="glass-card p-0 rounded-xl overflow-hidden group hover:border-blue-500/50 transition duration-300 relative">
                <!-- Top Badge -->
                <?php if($discount > 0): ?>
                    <div class="absolute top-3 right-3 bg-yellow-500 text-black text-[10px] font-bold px-2 py-1 rounded shadow-lg z-10">
                        -<?php echo $discount; ?>% AGENT
                    </div>
                <?php endif; ?>

                <!-- Product Body -->
                <div class="p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div class="w-12 h-12 bg-gray-800 rounded-lg flex items-center justify-center text-2xl text-blue-500 group-hover:scale-110 transition border border-gray-700">
                            <i class="fas fa-box-open"></i> <!-- Generic icon, can be category specific -->
                        </div>
                        <span class="text-[10px] uppercase font-bold tracking-wider text-gray-500 bg-gray-800 px-2 py-1 rounded border border-gray-700">
                            <?php echo htmlspecialchars($product['cat_name']); ?>
                        </span>
                    </div>

                    <h3 class="text-lg font-bold text-white mb-2 line-clamp-1 group-hover:text-blue-400 transition">
                        <?php echo htmlspecialchars($product['name']); ?>
                    </h3>
                    
                    <?php if($product['user_instruction']): ?>
                        <p class="text-xs text-gray-400 line-clamp-2 h-8 mb-4">
                            <?php echo htmlspecialchars($product['user_instruction']); ?>
                        </p>
                    <?php else: ?>
                        <p class="text-xs text-gray-500 mb-4 h-8">Instant delivery via email/chat.</p>
                    <?php endif; ?>

                    <!-- Price & Action -->
                    <div class="border-t border-gray-700 pt-4 flex items-center justify-between">
                        <div>
                            <div class="text-xs text-gray-500">Price</div>
                            <div class="flex items-baseline gap-2">
                                <?php if($discount > 0): ?>
                                    <span class="text-xs line-through text-gray-500"><?php echo format_price($product['price']); ?></span>
                                    <span class="text-xl font-bold text-yellow-400"><?php echo format_price($final_price); ?></span>
                                <?php else: ?>
                                    <span class="text-xl font-bold text-white"><?php echo format_price($product['price']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <a href="index.php?module=shop&page=checkout&id=<?php echo $product['id']; ?>" class="bg-blue-600 hover:bg-blue-700 text-white w-10 h-10 rounded-full flex items-center justify-center shadow-lg transform group-hover:scale-110 transition">
                            <i class="fas fa-shopping-cart text-sm"></i>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- SECTION 4: Browse Categories -->
<div>
    <h2 class="text-2xl font-bold text-white mb-6">Browse Categories</h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <?php foreach($categories as $cat): ?>
            <a href="index.php?module=shop&page=category&id=<?php echo $cat['id']; ?>" 
               class="glass-card p-6 rounded-xl hover:bg-gray-800 transition group text-center border border-gray-700 hover:border-gray-500">
                
                <div class="w-16 h-16 mx-auto bg-gray-900/50 rounded-full flex items-center justify-center mb-4 group-hover:scale-110 transition duration-300 border border-gray-700 group-hover:border-blue-500">
                    <i class="fas <?php echo $cat['icon_class']; ?> text-3xl text-gray-400 group-hover:text-blue-500 transition"></i>
                </div>
                
                <h3 class="text-lg font-bold text-white group-hover:text-blue-400 transition"><?php echo htmlspecialchars($cat['name']); ?></h3>
                <p class="text-xs text-gray-500 capitalize mt-1"><?php echo $cat['type']; ?></p>
            </a>
        <?php endforeach; ?>
    </div>
</div>