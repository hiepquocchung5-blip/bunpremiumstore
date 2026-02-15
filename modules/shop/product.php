<?php
// modules/shop/product.php

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 1. Handle Actions (Review & Wishlist)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!is_logged_in()) redirect('index.php?module=auth&page=login');
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) die("Invalid Token");

    $rating = (int)$_POST['rating'];
    $comment = trim($_POST['comment']);

    // Check Purchase
    $hasBought = $pdo->prepare("SELECT id FROM orders WHERE user_id = ? AND product_id = ? AND status = 'active'");
    $hasBought->execute([$_SESSION['user_id'], $product_id]);

    if ($hasBought->rowCount() > 0) {
        $stmt = $pdo->prepare("INSERT INTO reviews (user_id, product_id, rating, comment) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $product_id, $rating, $comment]);
        $success = "Review submitted!";
    } else {
        $error = "You must purchase this product to review it.";
    }
}

// Wishlist Logic
if (isset($_GET['wishlist'])) {
    if (!is_logged_in()) redirect('index.php?module=auth&page=login');
    
    if ($_GET['wishlist'] == 'add') {
        try {
            $pdo->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)")->execute([$_SESSION['user_id'], $product_id]);
        } catch (Exception $e) {} // Ignore duplicates
    } else {
        $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?")->execute([$_SESSION['user_id'], $product_id]);
    }
    redirect("index.php?module=shop&page=product&id=$product_id");
}

// 2. Fetch Product Data
// Note: We select p.* which includes the new 'image_path' column if it exists
$stmt = $pdo->prepare("
    SELECT p.*, c.name as cat_name, c.icon_class 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    WHERE p.id = ?
");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    echo "<div class='p-20 text-center text-gray-500'>Product not found. <a href='index.php' class='text-blue-400'>Go Home</a></div>";
    return;
}

// 3. Fetch Related Products
$stmt = $pdo->prepare("
    SELECT p.*, c.name as cat_name, c.icon_class
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.category_id = ? AND p.id != ?
    ORDER BY RAND() LIMIT 3
");
$stmt->execute([$product['category_id'], $product_id]);
$related_items = $stmt->fetchAll();

// 4. Fetch Reviews
$stmt = $pdo->prepare("SELECT r.*, u.username FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = ? ORDER BY r.created_at DESC");
$stmt->execute([$product_id]);
$reviews = $stmt->fetchAll();

// Stats
$avg_rating = count($reviews) > 0 ? round(array_sum(array_column($reviews, 'rating')) / count($reviews), 1) : 0;
$in_wishlist = false;
if (is_logged_in()) {
    $check = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
    $check->execute([$_SESSION['user_id'], $product_id]);
    $in_wishlist = $check->rowCount() > 0;
}

// Pricing
$discount = is_logged_in() ? get_user_discount($_SESSION['user_id']) : 0;
$base_price = $product['sale_price'] ?: $product['price'];
$final_price = $base_price * ((100 - $discount) / 100);

// Determine Image to Show (Real Image vs Icon)
$has_image = !empty($product['image_path']) && file_exists($product['image_path']);
$display_image = $has_image ? BASE_URL . $product['image_path'] : null;
?>

<style>
    .glass-card {
        background: rgba(31, 41, 55, 0.7);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    /* Lightbox Styles */
    .lightbox {
        display: none;
        position: fixed;
        z-index: 1000;
        padding-top: 100px;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.9);
        backdrop-filter: blur(5px);
    }
    .lightbox-content {
        margin: auto;
        display: block;
        width: 80%;
        max-width: 700px;
        border-radius: 8px;
        box-shadow: 0 0 50px rgba(37, 99, 235, 0.3);
    }
    .close-lightbox {
        position: absolute;
        top: 30px;
        right: 35px;
        color: #f1f1f1;
        font-size: 40px;
        font-weight: bold;
        transition: 0.3s;
        cursor: pointer;
    }
    .close-lightbox:hover { color: #bbb; }
</style>

<div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- LEFT: Main Content -->
    <div class="lg:col-span-2 space-y-8">
        
        <!-- Hero Section -->
        <div class="glass p-8 rounded-2xl border border-gray-700 relative overflow-hidden group">
            
            <!-- Dynamic Background -->
            <div class="absolute inset-0 opacity-10 pointer-events-none bg-gradient-to-r from-blue-900 via-gray-900 to-gray-900"></div>
            <?php if($has_image): ?>
                <div class="absolute -right-20 -top-20 opacity-20 blur-3xl pointer-events-none">
                    <img src="<?php echo $display_image; ?>" class="w-96 h-96 object-cover rounded-full">
                </div>
            <?php else: ?>
                <div class="absolute -right-6 -top-6 p-6 opacity-5 pointer-events-none">
                    <i class="fas <?php echo htmlspecialchars($product['icon_class']); ?> text-9xl text-white"></i>
                </div>
            <?php endif; ?>
            
            <div class="flex flex-col md:flex-row gap-8 relative z-10">
                <!-- Product Image/Icon Container -->
                <div class="shrink-0 mx-auto md:mx-0">
                    <?php if($has_image): ?>
                        <div class="w-48 h-48 rounded-2xl overflow-hidden shadow-2xl border-4 border-gray-800 relative cursor-zoom-in hover:scale-105 transition duration-300" onclick="openLightbox(this.querySelector('img').src)">
                            <img src="<?php echo $display_image; ?>" class="w-full h-full object-cover">
                            <div class="absolute inset-0 bg-black/20 hover:bg-transparent transition"></div>
                        </div>
                    <?php else: ?>
                        <div class="w-32 h-32 bg-gradient-to-br from-gray-800 to-gray-900 rounded-2xl flex items-center justify-center text-5xl text-blue-500 shadow-xl border border-gray-700">
                            <i class="fas <?php echo htmlspecialchars($product['icon_class']); ?>"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="flex-1 text-center md:text-left">
                    <div class="flex flex-col md:flex-row justify-between items-center md:items-start gap-4">
                        <div>
                            <span class="inline-block text-[10px] font-bold text-blue-400 bg-blue-900/20 px-2 py-1 rounded border border-blue-500/20 uppercase tracking-wider mb-2">
                                <?php echo htmlspecialchars($product['cat_name']); ?>
                            </span>
                            <h1 class="text-3xl font-bold text-white mb-2 leading-tight"><?php echo htmlspecialchars($product['name']); ?></h1>
                            
                            <div class="flex items-center justify-center md:justify-start gap-3 text-sm text-gray-400">
                                <div class="flex text-yellow-400 text-xs">
                                    <?php for($i=1; $i<=5; $i++) echo ($i <= $avg_rating) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star text-gray-600"></i>'; ?>
                                </div>
                                <span class="font-medium text-white"><?php echo $avg_rating; ?></span>
                                <span class="w-1 h-1 bg-gray-600 rounded-full"></span>
                                <span><?php echo count($reviews); ?> Reviews</span>
                            </div>
                        </div>
                        
                        <!-- Wishlist Toggle -->
                        <?php if(is_logged_in()): ?>
                            <a href="index.php?module=shop&page=product&id=<?php echo $product['id']; ?>&wishlist=<?php echo $in_wishlist ? 'remove' : 'add'; ?>" 
                               class="w-10 h-10 rounded-full flex items-center justify-center border transition <?php echo $in_wishlist ? 'bg-red-500/10 border-red-500/50 text-red-500' : 'bg-gray-800 border-gray-600 text-gray-400 hover:text-white hover:border-gray-500'; ?>"
                               title="<?php echo $in_wishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>">
                                <i class="<?php echo $in_wishlist ? 'fas' : 'far'; ?> fa-heart"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="mt-6 p-5 bg-gray-800/50 rounded-xl border border-gray-700/50 text-gray-300 leading-relaxed text-sm text-left">
                        <?php echo nl2br(htmlspecialchars($product['description'] ?? "Premium digital product. Instant delivery guaranteed.")); ?>
                    </div>
                    
                    <?php if($product['user_instruction']): ?>
                        <div class="mt-4 flex items-start gap-3 text-sm text-yellow-200/90 bg-yellow-900/10 p-3 rounded-lg border border-yellow-500/20 text-left">
                            <i class="fas fa-exclamation-triangle mt-0.5 text-yellow-500 shrink-0"></i>
                            <p><?php echo htmlspecialchars($product['user_instruction']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Related Products -->
        <?php if(!empty($related_items)): ?>
            <div>
                <h3 class="text-xl font-bold text-white mb-4">Related Products</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php 
                    // Reuse Product Card Logic
                    foreach($related_items as $item) {
                        // Map $item to $product for the include
                        $product_temp = $product; // Save main product
                        $product = $item; 
                        include __DIR__ . '/../home/product_card.php';
                        $product = $product_temp; // Restore
                    } 
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Reviews Section -->
        <div class="glass p-8 rounded-2xl border border-gray-700">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-white">Reviews</h3>
                <span class="text-xs text-gray-500 bg-gray-800 px-2 py-1 rounded"><?php echo count($reviews); ?> Total</span>
            </div>
            
            <?php if(isset($success)) echo "<div class='bg-green-500/20 text-green-400 p-3 rounded mb-6 text-sm border border-green-500/30 flex items-center gap-2'><i class='fas fa-check-circle'></i> $success</div>"; ?>
            <?php if(isset($error)) echo "<div class='bg-red-500/20 text-red-400 p-3 rounded mb-6 text-sm border border-red-500/30 flex items-center gap-2'><i class='fas fa-exclamation-circle'></i> $error</div>"; ?>

            <!-- Write Review -->
            <?php if(is_logged_in()): ?>
                <form method="POST" class="mb-8 p-5 bg-gray-800/30 rounded-xl border border-gray-700/50 transition focus-within:border-gray-600">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="flex items-center gap-4 mb-3">
                        <span class="text-sm text-gray-400">Your Rating:</span>
                        <div class="flex flex-row-reverse justify-end gap-1 group">
                            <?php for($i=5; $i>=1; $i--): ?>
                                <input type="radio" name="rating" value="<?php echo $i; ?>" id="star<?php echo $i; ?>" class="peer hidden" required>
                                <label for="star<?php echo $i; ?>" class="cursor-pointer text-gray-600 peer-checked:text-yellow-400 hover:text-yellow-400 peer-hover:text-yellow-400 transition text-lg"><i class="fas fa-star"></i></label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <textarea name="comment" rows="2" placeholder="Share your experience..." required class="w-full bg-gray-900 border border-gray-600 rounded-lg p-3 text-white text-sm focus:border-blue-500 outline-none transition placeholder-gray-600"></textarea>
                    <button type="submit" name="submit_review" class="mt-3 bg-blue-600 hover:bg-blue-500 text-white px-5 py-2 rounded-lg text-sm font-bold transition shadow-lg flex items-center gap-2">
                        <i class="fas fa-paper-plane"></i> Post Review
                    </button>
                </form>
            <?php else: ?>
                <div class="mb-8 p-6 bg-gray-800/30 rounded-xl border border-gray-700 text-center">
                    <p class="text-gray-400 text-sm mb-2">Want to share your thoughts?</p>
                    <a href="index.php?module=auth&page=login" class="text-blue-400 hover:text-blue-300 font-bold text-sm">Login to leave a review</a>
                </div>
            <?php endif; ?>

            <!-- Review List -->
            <div class="space-y-6">
                <?php if(empty($reviews)): ?>
                    <div class="text-center py-10 text-gray-500 bg-gray-800/20 rounded-xl border border-gray-800">
                        <i class="far fa-comment-dots text-4xl mb-3 opacity-30"></i>
                        <p class="text-sm">No reviews yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($reviews as $rev): ?>
                        <div class="border-b border-gray-700 pb-6 last:border-0 last:pb-0">
                            <div class="flex justify-between items-center mb-2">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-gray-700 to-gray-600 flex items-center justify-center text-xs font-bold text-white shadow-inner">
                                        <?php echo strtoupper(substr($rev['username'], 0, 1)); ?>
                                    </div>
                                    <span class="text-sm font-bold text-gray-200"><?php echo htmlspecialchars($rev['username']); ?></span>
                                </div>
                                <span class="text-[10px] text-gray-500"><?php echo date('M d, Y', strtotime($rev['created_at'])); ?></span>
                            </div>
                            <div class="flex text-yellow-500 text-[10px] mb-2 pl-11">
                                <?php for($i=1; $i<=5; $i++) echo ($i <= $rev['rating']) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star text-gray-600"></i>'; ?>
                            </div>
                            <p class="text-gray-400 text-sm pl-11 leading-relaxed">"<?php echo htmlspecialchars($rev['comment']); ?>"</p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- RIGHT: Pricing & Upsell -->
    <div class="lg:col-span-1 space-y-6">
        
        <!-- Checkout Card -->
        <div class="glass p-6 rounded-2xl border border-gray-700 sticky top-24 shadow-2xl">
            <h3 class="text-gray-400 text-xs uppercase font-bold mb-4 tracking-widest flex items-center gap-2">
                <i class="fas fa-receipt"></i> Order Summary
            </h3>
            
            <div class="space-y-3 mb-6 border-b border-gray-700 pb-6">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-400">Regular Price</span>
                    <span class="text-white <?php echo ($discount > 0 || $product['sale_price']) ? 'line-through decoration-gray-500 text-gray-500' : ''; ?>">
                        <?php echo format_price($product['price']); ?>
                    </span>
                </div>
                
                <?php if($product['sale_price']): ?>
                <div class="flex justify-between text-sm">
                    <span class="text-red-400">Sale Savings</span>
                    <span class="text-red-400 font-bold">- <?php echo format_price($product['price'] - $product['sale_price']); ?></span>
                </div>
                <?php endif; ?>

                <?php if($discount > 0): ?>
                <div class="flex justify-between text-sm">
                    <span class="text-yellow-400 flex items-center gap-1"><i class="fas fa-crown text-xs"></i> Agent Discount</span>
                    <span class="text-yellow-400 font-bold">- <?php echo $discount; ?>%</span>
                </div>
                <?php endif; ?>
            </div>

            <div class="flex justify-between items-end mb-6">
                <span class="text-gray-300 font-bold text-sm">Total Pay</span>
                <span class="text-3xl font-bold text-green-400 tracking-tight"><?php echo format_price($final_price); ?></span>
            </div>

            <a href="index.php?module=shop&page=checkout&id=<?php echo $product['id']; ?>" class="block w-full bg-gradient-to-r from-green-600 to-green-500 hover:from-green-500 hover:to-green-400 text-white font-bold py-4 rounded-xl text-center shadow-lg transform hover:scale-[1.02] transition duration-200 group">
                Buy Now <i class="fas fa-arrow-right ml-2 text-sm group-hover:translate-x-1 transition-transform"></i>
            </a>
            
            <div class="mt-4 grid grid-cols-2 gap-2 text-[10px] text-gray-500 font-medium text-center">
                <span class="bg-gray-800/50 py-1.5 rounded border border-gray-700/50"><i class="fas fa-bolt mr-1 text-yellow-500"></i> Instant</span>
                <span class="bg-gray-800/50 py-1.5 rounded border border-gray-700/50"><i class="fas fa-shield-alt mr-1 text-blue-500"></i> Secure</span>
            </div>
        </div>

    </div>
</div>

<!-- Lightbox Element -->
<div id="productLightbox" class="lightbox" onclick="closeLightbox()">
    <span class="close-lightbox">&times;</span>
    <img class="lightbox-content" id="lightboxImg">
</div>

<script>
    function openLightbox(src) {
        const lightbox = document.getElementById('productLightbox');
        const img = document.getElementById('lightboxImg');
        img.src = src;
        lightbox.style.display = "flex";
        // Prevent body scroll
        document.body.style.overflow = "hidden";
    }
    
    function closeLightbox() {
        document.getElementById('productLightbox').style.display = "none";
        document.body.style.overflow = "auto";
    }
</script>