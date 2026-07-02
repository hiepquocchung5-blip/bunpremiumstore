<?php
// modules/shop/product.php
// PRODUCTION v4.3 - Mobile DOM Reordering & Strict Flash Sale Math

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product_slug = trim($_GET['slug'] ?? '');

// 1. Resolve Product Data before any action so slug routes use the real product id.
$product = resolve_product_route($product_id, $product_slug);

if (!$product) {
    echo "<div class='p-20 text-center text-slate-500'>Product not found. <a href='index.php' class='text-[#00f0ff] hover:underline'>Return to Hub</a></div>";
    return;
}

$product_id = (int)$product['id'];
$product_url = product_public_url($product);

// 2. Handle Actions (Review & Wishlist)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!is_logged_in()) redirect('index.php?module=auth&page=login');
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) die("Invalid Token");

    $rating = max(1, min(5, (int)($_POST['rating'] ?? 0)));
    $comment = trim($_POST['comment'] ?? '');

    // Check Purchase
    $hasBought = $pdo->prepare("SELECT id FROM orders WHERE user_id = ? AND product_id = ? AND status = 'active'");
    $hasBought->execute([$_SESSION['user_id'], $product_id]);

    if ($hasBought->rowCount() > 0) {
        $existingReview = $pdo->prepare("SELECT id FROM reviews WHERE user_id = ? AND product_id = ? LIMIT 1");
        $existingReview->execute([$_SESSION['user_id'], $product_id]);
        $review_id = $existingReview->fetchColumn();

        if ($review_id) {
            $stmt = $pdo->prepare("UPDATE reviews SET rating = ?, comment = ?, created_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$rating, $comment, $review_id]);
            $success = "Review updated successfully!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO reviews (user_id, product_id, rating, comment) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $product_id, $rating, $comment]);
            $success = "Review submitted successfully!";
        }
    } else {
        $error = "You must successfully purchase this product before reviewing it.";
    }
}

// Wishlist Logic
if (isset($_GET['wishlist'])) {
    if (!is_logged_in()) redirect('index.php?module=auth&page=login');
    
    if ($_GET['wishlist'] == 'add') {
        try {
            $pdo->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)")->execute([$_SESSION['user_id'], $product_id]);
            matrix_cache_delete("user_wishlist_count_{$_SESSION['user_id']}");
        } catch (Exception $e) {} // Ignore duplicates
    } else {
        $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?")->execute([$_SESSION['user_id'], $product_id]);
        matrix_cache_delete("user_wishlist_count_{$_SESSION['user_id']}");
    }
    redirect($product_url);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id']) && $product_slug === '') {
    redirect($product_url, 301);
}

// 3. Fetch Related Products
$stmt = $pdo->prepare("
    SELECT p.*, c.name as cat_name, c.image_url as cat_image
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.category_id = ? AND p.id != ? AND " . product_active_condition('p') . "
    ORDER BY RAND() LIMIT 4
");
$stmt->execute([$product['category_id'], $product_id]);
$related_items = $stmt->fetchAll();

// 4. Fetch Reviews
$stmt = $pdo->prepare("
    SELECT r.*, u.username,
           EXISTS (
               SELECT 1 FROM orders o
               WHERE o.user_id = r.user_id
                 AND o.product_id = r.product_id
                 AND o.status = 'active'
           ) as verified_purchase
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    WHERE r.product_id = ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$product_id]);
$reviews = $stmt->fetchAll();

// Stats
$avg_rating = count($reviews) > 0 ? round(array_sum(array_column($reviews, 'rating')) / count($reviews), 1) : 0;
$rating_counts = array_fill(1, 5, 0);
foreach ($reviews as $review_item) {
    $review_rating = max(1, min(5, (int)$review_item['rating']));
    $rating_counts[$review_rating]++;
}
$in_wishlist = false;
if (is_logged_in()) {
    $check = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
    $check->execute([$_SESSION['user_id'], $product_id]);
    $in_wishlist = $check->rowCount() > 0;
}

$user_has_bought = false;
if (is_logged_in()) {
    $purchaseCheck = $pdo->prepare("SELECT id FROM orders WHERE user_id = ? AND product_id = ? AND status = 'active' LIMIT 1");
    $purchaseCheck->execute([$_SESSION['user_id'], $product_id]);
    $user_has_bought = (bool)$purchaseCheck->fetchColumn();
}

// ==========================================
// 5. STRICT PRICING & DISCOUNT MATH ENGINE
// ==========================================
// Ensure pure floating-point arithmetic to prevent string concatenation bugs
$discount = is_logged_in() ? (float)get_user_discount($_SESSION['user_id']) : 0.0;

$retail_value = (float)$product['price'];
$db_sale_price = (float)$product['sale_price'];

// Safely determine if a valid flash sale exists
$is_flash_sale = ($db_sale_price > 0 && $db_sale_price < $retail_value);

if ($is_flash_sale) {
    $current_payable = $db_sale_price; // Total Pay is explicitly the sale price from DB
    $flash_sale_deduction = $retail_value - $db_sale_price; // Display deduction as Retail - Sale
} else {
    $current_payable = $retail_value;
    $flash_sale_deduction = 0;
}

// Calculate Agent Savings dynamically
$agent_deduction = $current_payable * ($discount / 100);
$final_payable = $current_payable - $agent_deduction;

// Absolute Failsafe: Price cannot drop below 0
$final_payable = max(0, $final_payable);

// ==========================================
// 6. Stock Check Logic
// ==========================================
$can_buy = true;
$stock_status_html = '';

if ($product['delivery_type'] === 'unique') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_keys WHERE product_id = ? AND is_sold = 0 AND order_id IS NULL");
    $stmt->execute([$product_id]);
    $stock_count = $stmt->fetchColumn();
    
    if ($stock_count == 0) {
        $can_buy = false;
        $stock_status_html = '<span class="bg-red-500/10 border border-red-500/30 text-red-400 px-3 py-1 rounded-md text-[10px] font-bold uppercase tracking-widest"><i class="fas fa-times-circle"></i> Out of Stock</span>';
    } elseif ($stock_count <= 5) {
        $stock_status_html = '<span class="bg-orange-500/10 border border-orange-500/30 text-orange-400 px-3 py-1 rounded-md text-[10px] font-bold uppercase tracking-widest animate-pulse"><i class="fas fa-fire"></i> Only '.$stock_count.' Left</span>';
    } else {
        $stock_status_html = '<span class="bg-green-500/10 border border-green-500/30 text-green-400 px-3 py-1 rounded-md text-[10px] font-bold uppercase tracking-widest"><i class="fas fa-check-circle"></i> In Stock</span>';
    }
} else {
    // Universal or Form delivery assumes unlimited digital capacity
    $stock_status_html = '<span class="bg-blue-500/10 border border-blue-500/30 text-blue-400 px-3 py-1 rounded-md text-[10px] font-bold uppercase tracking-widest"><i class="fas fa-infinity"></i> Unlimited Flow</span>';
}

// 7. Image Check
$has_image = !empty($product['image_path']) && file_exists($product['image_path']);
$display_image = $has_image ? BASE_URL . $product['image_path'] : null;
$has_cat_image = !empty($product['cat_image']);

$schema_image = $has_image ? $display_image : ($has_cat_image ? BASE_URL . $product['cat_image'] : BASE_URL . 'assets/images/og-image.png');
$schema_data = [
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $product['name'],
    'description' => trim($product['description'] ?: $product['user_instruction'] ?: 'Digital product available with instant delivery.'),
    'image' => $schema_image,
    'category' => $product['cat_name'] ?? 'Digital Goods',
    'brand' => [
        '@type' => 'Brand',
        'name' => 'DigitalMM'
    ],
    'offers' => [
        '@type' => 'Offer',
        'priceCurrency' => 'MMK',
        'price' => number_format($final_payable, 0, '.', ''),
        'availability' => $can_buy ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        'url' => $product_url
    ]
];
if ($avg_rating > 0) {
    $schema_data['aggregateRating'] = [
        '@type' => 'AggregateRating',
        'ratingValue' => (string)$avg_rating,
        'reviewCount' => (string)count($reviews)
    ];
}
?>

<script type="application/ld+json">
<?php echo json_encode($schema_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); ?>
</script>

<style>
    /* Lightbox Styles */
    .lightbox { display: none; position: fixed; z-index: 1000; padding-top: 100px; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(15, 23, 42, 0.95); backdrop-filter: blur(10px); }
    .lightbox-content { margin: auto; display: block; width: 90%; max-width: 800px; border-radius: 16px; box-shadow: 0 0 50px rgba(0, 240, 255, 0.3); border: 1px solid rgba(0, 240, 255, 0.2); }
    .close-lightbox { position: absolute; top: 30px; right: 35px; color: #94a3b8; font-size: 40px; font-weight: bold; transition: 0.3s; cursor: pointer; }
    .close-lightbox:hover { color: #fff; }
    
    /* Tab Transitions */
    .tab-content { display: none; animation: fadeIn 0.3s ease-in-out; }
    .tab-content.active { display: block; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="max-w-7xl mx-auto px-4 py-12">
    <?php if(isset($success)): ?>
        <div class="mb-6 bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 px-5 py-4 rounded-2xl text-sm font-bold flex items-center gap-3">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>
    <?php if(isset($error)): ?>
        <div class="mb-6 bg-rose-500/10 border border-rose-500/30 text-rose-300 px-5 py-4 rounded-2xl text-sm font-bold flex items-center gap-3">
            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <!-- Breadcrumbs -->
    <div class="mb-8 flex items-center justify-between">
        <a href="<?php echo BASE_URL; ?>" class="inline-flex items-center gap-2 text-sm font-bold text-slate-500 hover:text-white transition group">
            <i class="fas fa-arrow-left group-hover:-translate-x-1 transition-transform"></i> Back to Store
        </a>
        
        <button onclick="shareProduct()" class="liquid-glass-btn liquid-glass-share text-xs px-4 py-2">
            <i class="fas fa-share-alt"></i> <span id="shareText">Share Link</span>
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
        
        <!-- Product Main -->
        <div class="lg:col-span-8 space-y-12">
            
            <div class="bg-slate-800/20 rounded-[2.5rem] p-8 md:p-12 border border-white/5 flex flex-col md:flex-row gap-10">
                
                <!-- Image -->
                <div class="shrink-0">
                    <div class="w-full max-w-[280px] mx-auto md:mx-0 md:w-64 aspect-[4/3] md:aspect-square rounded-3xl overflow-hidden bg-slate-900 border border-white/10 shadow-2xl relative group">
                        <?php if($has_image): ?>
                            <img src="<?php echo $display_image; ?>" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110" loading="eager" fetchpriority="high">
                        <?php elseif($has_cat_image): ?>
                            <img src="<?php echo BASE_URL . $product['cat_image']; ?>" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110" loading="eager" fetchpriority="high">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center text-6xl text-slate-700">
                                <i class="fas fa-box"></i>
                            </div>
                        <?php endif; ?>
                        
                        <?php if(is_logged_in()): ?>
                            <a href="<?php echo BASE_URL; ?>index.php?module=shop&page=product&id=<?php echo $product['id']; ?>&wishlist=<?php echo $in_wishlist ? 'remove' : 'add'; ?>" 
                               class="liquid-glass-btn liquid-glass-like absolute top-4 right-4 w-11 h-11 !p-0 rounded-full text-sm z-20 <?php echo $in_wishlist ? 'bg-rose-500/90 text-white border-rose-200/40' : ''; ?>">
                                <i class="<?php echo $in_wishlist ? 'fas' : 'far'; ?> fa-heart"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Info -->
                <div class="flex-1 space-y-6">
                    <div>
                        <div class="flex items-center gap-3 mb-4">
                            <span class="px-3 py-1 bg-blue-500/10 text-blue-400 text-[10px] font-bold uppercase tracking-widest rounded-lg border border-blue-500/20">
                                <?php echo htmlspecialchars($product['cat_name']); ?>
                            </span>
                            <?php echo $stock_status_html; ?>
                        </div>
                        <h1 class="text-3xl md:text-5xl font-bold text-white leading-tight tracking-tight"><?php echo htmlspecialchars($product['name']); ?></h1>
                    </div>

                    <div class="flex items-center gap-4 text-sm">
                        <div class="flex text-amber-400 gap-1">
                            <?php for($i=1; $i<=5; $i++) echo ($i <= $avg_rating) ? '<i class="fas fa-star text-xs"></i>' : '<i class="far fa-star text-xs text-slate-700"></i>'; ?>
                        </div>
                        <span class="font-bold text-white"><?php echo $avg_rating; ?></span>
                        <span class="text-slate-600">|</span>
                        <span class="text-slate-400"><?php echo count($reviews); ?> Reviews</span>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-slate-900/40 p-4 rounded-2xl border border-white/5 flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center text-emerald-400">
                                <i class="fas <?php echo $product['delivery_type'] == 'unique' ? 'fa-bolt' : 'fa-clock'; ?>"></i>
                            </div>
                            <div>
                                <p class="text-[9px] text-slate-500 font-bold uppercase tracking-widest mb-0.5">Delivery</p>
                                <p class="text-xs text-white font-medium"><?php echo $product['delivery_type'] == 'unique' ? 'Instant' : '5-15 Mins'; ?></p>
                            </div>
                        </div>
                        <div class="bg-slate-900/40 p-4 rounded-2xl border border-white/5 flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-indigo-500/10 flex items-center justify-center text-indigo-400">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div>
                                <p class="text-[9px] text-slate-500 font-bold uppercase tracking-widest mb-0.5">Warranty</p>
                                <p class="text-xs text-white font-medium"><?php echo $product['duration_days'] ? $product['duration_days'].' Days' : 'Lifetime'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Tabs -->
            <div class="bg-slate-800/20 rounded-[2.5rem] border border-white/5 overflow-hidden">
                <div class="flex border-b border-white/5 bg-white/5 px-4 pt-4 overflow-x-auto no-scrollbar">
                    <button onclick="switchTab('desc')" id="btn-tab-desc" class="px-8 py-4 text-sm font-bold border-b-2 border-blue-500 text-white transition-all whitespace-nowrap">
                        Description
                    </button>
                    <button onclick="switchTab('inst')" id="btn-tab-inst" class="px-8 py-4 text-sm font-bold border-b-2 border-transparent text-slate-500 hover:text-white transition-all whitespace-nowrap">
                        How to Use
                    </button>
                    <button onclick="switchTab('rev')" id="btn-tab-rev" class="px-8 py-4 text-sm font-bold border-b-2 border-transparent text-slate-500 hover:text-white transition-all whitespace-nowrap">
                        Reviews (<?php echo count($reviews); ?>)
                    </button>
                </div>

                <div class="p-8 md:p-12">
                    <!-- Description -->
                    <div id="tab-desc" class="tab-content active text-slate-400 text-sm leading-relaxed space-y-4">
                        <?php echo nl2br(htmlspecialchars($product['description'] ?: "Detailed information for this product is coming soon.")); ?>
                    </div>

                    <!-- Instructions -->
                    <div id="tab-inst" class="tab-content">
                        <?php if($product['user_instruction']): ?>
                            <div class="bg-amber-500/5 p-6 rounded-3xl border border-amber-500/20 mb-8">
                                <div class="flex gap-4">
                                    <i class="fas fa-info-circle text-amber-500 mt-1"></i>
                                    <div>
                                        <h4 class="font-bold text-amber-500 mb-2">Important Note</h4>
                                        <p class="text-sm text-slate-400 leading-relaxed"><?php echo htmlspecialchars($product['user_instruction']); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="bg-slate-900/40 rounded-2xl p-6 border border-white/5">
                            <h4 class="font-bold text-white mb-4 text-xs uppercase tracking-widest">Delivery Process</h4>
                            <p class="text-sm text-slate-400 leading-relaxed">
                                <?php 
                                    if($product['delivery_type'] == 'unique') echo "After payment, your unique digital key will be sent instantly to your Order Chat.";
                                    elseif($product['delivery_type'] == 'form') echo "Please provide the required details during checkout. Our team will process it manually.";
                                    else echo "Your product details will be delivered to your Order Chat after verification.";
                                ?>
                            </p>
                        </div>
                    </div>

                    <!-- Reviews -->
                    <div id="tab-rev" class="tab-content space-y-10">
                        <?php if(!empty($reviews)): ?>
                            <div class="bg-slate-900/40 p-6 rounded-3xl border border-white/5">
                                <div class="flex flex-col md:flex-row md:items-center gap-8">
                                    <div class="text-center md:w-40 shrink-0">
                                        <div class="text-5xl font-black text-white"><?php echo $avg_rating; ?></div>
                                        <div class="flex justify-center text-amber-400 gap-1 mt-2">
                                            <?php for($i=1; $i<=5; $i++) echo ($i <= $avg_rating) ? '<i class="fas fa-star text-xs"></i>' : '<i class="far fa-star text-xs text-slate-700"></i>'; ?>
                                        </div>
                                        <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest mt-2"><?php echo count($reviews); ?> Reviews</p>
                                    </div>
                                    <div class="flex-1 space-y-2">
                                        <?php for($star = 5; $star >= 1; $star--): ?>
                                            <?php $pct = count($reviews) > 0 ? ($rating_counts[$star] / count($reviews)) * 100 : 0; ?>
                                            <div class="grid grid-cols-[48px_1fr_32px] items-center gap-3 text-xs">
                                                <span class="text-slate-400 font-bold"><?php echo $star; ?> Star</span>
                                                <div class="h-2 bg-slate-800 rounded-full overflow-hidden">
                                                    <div class="h-full bg-amber-400 rounded-full" style="width: <?php echo $pct; ?>%"></div>
                                                </div>
                                                <span class="text-slate-500 text-right"><?php echo $rating_counts[$star]; ?></span>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if(is_logged_in()): ?>
                            <form method="POST" class="bg-slate-900/40 p-8 rounded-3xl border border-white/5 space-y-6">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                    <span class="text-sm font-bold text-white">Write a Review</span>
                                    <div class="flex flex-row-reverse gap-2">
                                        <?php for($i=5; $i>=1; $i--): ?>
                                            <input type="radio" name="rating" value="<?php echo $i; ?>" id="star<?php echo $i; ?>" class="peer hidden" required>
                                            <label for="star<?php echo $i; ?>" class="cursor-pointer text-slate-800 peer-checked:text-amber-400 hover:text-amber-400 transition-colors text-2xl"><i class="fas fa-star"></i></label>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <textarea name="comment" rows="3" placeholder="Share your experience with this product..." required class="w-full bg-slate-800/50 border border-white/10 rounded-2xl p-4 text-white text-sm focus:border-blue-500 outline-none transition-all placeholder-slate-600"></textarea>
                                <div class="flex justify-end">
                                    <button type="submit" name="submit_review" class="bg-blue-600 hover:bg-blue-500 text-white px-8 py-3 rounded-xl font-bold transition-all active:scale-95">Post Review</button>
                                </div>
                            </form>
                        <?php endif; ?>

                        <div class="space-y-6">
                            <?php if(empty($reviews)): ?>
                                <div class="text-center py-10">
                                    <p class="text-slate-600 text-sm italic">No reviews yet. Be the first to share!</p>
                                </div>
                            <?php else: ?>
                                <?php foreach($reviews as $rev): ?>
                                    <div class="p-6 rounded-3xl bg-slate-900/20 border border-white/5 space-y-4">
                                        <div class="flex justify-between items-start">
                                            <div class="flex items-center gap-4">
                                                <div class="w-10 h-10 rounded-xl bg-slate-800 flex items-center justify-center text-xs font-bold text-white">
                                                    <?php echo strtoupper(substr($rev['username'], 0, 2)); ?>
                                                </div>
                                                <div>
                                                    <span class="text-sm font-bold text-white">@<?php echo htmlspecialchars($rev['username']); ?></span>
                                                    <?php if(!empty($rev['verified_purchase'])): ?>
                                                        <span class="inline-flex items-center gap-1 text-[9px] text-emerald-300 bg-emerald-500/10 border border-emerald-500/20 px-2 py-0.5 rounded-full font-bold uppercase tracking-widest mt-1">
                                                            <i class="fas fa-check-circle"></i> Verified
                                                        </span>
                                                    <?php endif; ?>
                                                    <div class="flex text-amber-400 text-[10px] mt-1">
                                                        <?php for($i=1; $i<=5; $i++) echo ($i <= $rev['rating']) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star text-slate-800"></i>'; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <span class="text-[10px] text-slate-600"><?php echo date('M d, Y', strtotime($rev['created_at'])); ?></span>
                                        </div>
                                        <p class="text-slate-400 text-sm leading-relaxed italic">"<?php echo htmlspecialchars($rev['comment']); ?>"</p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Sidebar / Checkout -->
        <div class="lg:col-span-4">
            
            <div class="bg-slate-800/20 rounded-[2.5rem] p-8 border border-white/5 space-y-8 sticky top-32 shadow-2xl">
                <h3 class="text-white font-bold text-lg border-b border-white/5 pb-6">Order Summary</h3>
                
                <div class="space-y-4 bg-slate-900/40 p-6 rounded-2xl border border-white/5">
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Retail Price</span>
                        <span class="<?php echo ($is_flash_sale || $discount > 0) ? 'text-slate-600 line-through' : 'text-white font-bold'; ?>">
                            <?php echo format_price($retail_value); ?>
                        </span>
                    </div>
                    
                    <?php if($is_flash_sale): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-rose-500 font-bold">Flash Sale</span>
                        <span class="text-rose-500 font-bold">- <?php echo format_price($flash_sale_deduction); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if($discount > 0): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-amber-500 font-bold">Agent Discount (<?php echo $discount; ?>%)</span>
                        <span class="text-amber-500 font-bold">- <?php echo format_price($agent_deduction); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="pt-4 border-t border-white/5 flex justify-between items-end">
                        <span class="text-white font-bold">Total Pay</span>
                        <span class="text-3xl font-bold text-blue-400"><?php echo format_price($final_payable); ?></span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <?php if($can_buy): ?>
                        <a href="<?php echo BASE_URL; ?>index.php?module=shop&page=checkout&id=<?php echo $product['id']; ?>" class="liquid-glass-btn liquid-glass-buy w-full py-4 text-center justify-center min-h-[60px] shadow-lg hover:shadow-orange-500/20">
                            <i class="fas fa-bag-shopping"></i> <?php echo $user_has_bought ? 'Buy Again' : 'Buy Now'; ?>
                        </a>
                    <?php else: ?>
                        <button disabled class="liquid-glass-btn liquid-glass-buy w-full py-4 justify-center min-h-[60px] opacity-60 cursor-not-allowed">
                            <i class="fas fa-ban"></i> Out of Stock
                        </button>
                    <?php endif; ?>

                    <?php if(is_logged_in()): ?>
                        <a href="<?php echo BASE_URL; ?>index.php?module=shop&page=product&id=<?php echo $product['id']; ?>&wishlist=<?php echo $in_wishlist ? 'remove' : 'add'; ?>" class="liquid-glass-btn liquid-glass-like w-full py-4 text-center justify-center min-h-[60px] <?php echo $in_wishlist ? 'border-rose-300/60 bg-rose-500/15 text-rose-200' : ''; ?> shadow-lg hover:shadow-rose-500/10">
                            <i class="<?php echo $in_wishlist ? 'fas' : 'far'; ?> fa-heart"></i>
                            <?php echo $in_wishlist ? 'Liked' : 'Like'; ?>
                        </a>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>index.php?module=auth&page=login" class="liquid-glass-btn liquid-glass-like w-full py-4 text-center justify-center min-h-[60px] shadow-lg">
                            <i class="far fa-heart"></i> Like
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="text-center p-4 bg-slate-900/40 rounded-2xl border border-white/5">
                        <i class="fas fa-check-circle text-emerald-500 text-xl mb-2"></i>
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Verified</p>
                    </div>
                    <div class="text-center p-4 bg-slate-900/40 rounded-2xl border border-white/5">
                        <i class="fas fa-headset text-blue-500 text-xl mb-2"></i>
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Support</p>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<?php if($can_buy): ?>
<div class="lg:hidden fixed bottom-0 left-0 right-0 z-50 bg-slate-950/95 border-t border-white/10 backdrop-blur-xl px-4 py-3">
    <div class="flex items-center justify-between gap-4">
        <div class="min-w-0">
            <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest truncate"><?php echo htmlspecialchars($product['name']); ?></p>
            <p class="text-xl font-black text-blue-400"><?php echo format_price($final_payable); ?></p>
        </div>
        <a href="<?php echo BASE_URL; ?>index.php?module=shop&page=checkout&id=<?php echo $product['id']; ?>" class="liquid-glass-btn liquid-glass-buy px-5 py-3 text-sm shrink-0">
            <i class="fas fa-bag-shopping"></i> <?php echo $user_has_bought ? 'Buy Again' : 'Buy Now'; ?>
        </a>
    </div>
</div>
<?php endif; ?>

<script>
    window.switchTab = function(tabId) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.getElementById('tab-' + tabId).classList.add('active');
        
        document.querySelectorAll('[id^="btn-tab-"]').forEach(el => {
            el.classList.remove('border-blue-500', 'text-white', 'bg-white/5');
            el.classList.add('border-transparent', 'text-slate-500');
        });
        document.getElementById('btn-tab-' + tabId).classList.add('border-blue-500', 'text-white', 'bg-white/5');
        document.getElementById('btn-tab-' + tabId).classList.remove('border-transparent', 'text-slate-500');
    };

    window.shareProduct = async function() {
        const btnText = document.getElementById('shareText');
        const payload = {
            title: <?php echo json_encode($product['name']); ?>,
            text: '',
            url: <?php echo json_encode($product_url); ?>
        };
        const ok = typeof window.shareCurrentPage === 'function'
            ? await window.shareCurrentPage(payload)
            : await (async () => {
                try {
                    if (navigator.share) {
                        await navigator.share(payload);
                        return true;
                    }
                    if (navigator.clipboard && payload.url) {
                        await navigator.clipboard.writeText(payload.url);
                        return true;
                    }
                } catch (e) {}
                return false;
            })();
        if (btnText) {
            btnText.innerText = ok ? 'Copied!' : 'Share Link';
            setTimeout(() => { btnText.innerText = 'Share Link'; }, 2000);
        }
    };

    (() => {
        const current = {
            id: <?php echo (int)$product['id']; ?>,
            name: <?php echo json_encode($product['name']); ?>,
            url: <?php echo json_encode($product_url); ?>,
            image: <?php echo json_encode($schema_image); ?>,
            price: <?php echo json_encode(format_price($final_payable)); ?>
        };
        const key = 'dm_recent_products';
        const existing = JSON.parse(localStorage.getItem(key) || '[]').filter(item => item && item.id !== current.id);
        const updated = [current, ...existing].slice(0, 8);
        localStorage.setItem(key, JSON.stringify(updated));

        const mount = document.getElementById('recentlyViewedGrid');
        if (!mount) return;
        const recent = updated.filter(item => item.id !== current.id).slice(0, 4);
        if (!recent.length) return;

        document.getElementById('recentlyViewedSection')?.classList.remove('hidden');
        const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char]));
        mount.innerHTML = recent.map(item => `
            <a href="${escapeHtml(item.url)}" class="bg-slate-900/40 border border-white/5 rounded-2xl overflow-hidden flex items-center gap-4 p-3 hover:border-blue-500/40 transition">
                <img src="${escapeHtml(item.image)}" alt="" class="w-16 h-16 rounded-xl object-cover bg-slate-800 shrink-0">
                <div class="min-w-0">
                    <h4 class="text-sm font-bold text-white truncate">${escapeHtml(item.name)}</h4>
                    <p class="text-xs text-blue-400 font-bold mt-1">${escapeHtml(item.price)}</p>
                </div>
            </a>
        `).join('');
    })();
</script>

<div id="recentlyViewedSection" class="hidden max-w-7xl mx-auto px-4 pb-4 pt-8">
    <div class="border-t border-white/5 pt-10">
        <h3 class="text-xl font-bold text-white mb-6 tracking-tight">Recently Viewed</h3>
        <div id="recentlyViewedGrid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4"></div>
    </div>
</div>

<!-- Related Products Grid -->
<?php if(!empty($related_items)): ?>
<div class="max-w-7xl mx-auto px-4 pb-16 pt-12">
    <div class="border-t border-white/5 pt-12">
        <h3 class="text-2xl font-bold text-white mb-10 tracking-tight">You might also like</h3>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
            <?php 
            foreach($related_items as $item) {
                $product_temp = $product; 
                $product = $item; 
                include __DIR__ . '/../home/product_card.php';
                $product = $product_temp; 
            } 
            ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Lightbox Element -->
<div id="productLightbox" class="lightbox flex" onclick="closeLightbox()" style="display:none;">
    <span class="close-lightbox">&times;</span>
    <img class="lightbox-content transform scale-95 transition-transform duration-300" id="lightboxImg">
</div>

<script>
    // Lightbox Logic
    function openLightbox(src) {
        const lightbox = document.getElementById('productLightbox');
        const img = document.getElementById('lightboxImg');
        img.src = src;
        lightbox.style.display = "flex";
        setTimeout(() => img.classList.remove('scale-95'), 10);
        document.body.style.overflow = "hidden";
    }
    
    function closeLightbox() {
        const lightbox = document.getElementById('productLightbox');
        const img = document.getElementById('lightboxImg');
        img.classList.add('scale-95');
        setTimeout(() => {
            lightbox.style.display = "none";
            document.body.style.overflow = "auto";
        }, 300);
    }
</script>
