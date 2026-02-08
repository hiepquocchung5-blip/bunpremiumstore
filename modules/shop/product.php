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
$related = $stmt->fetchAll();

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
?>

<style>
    .glass-card {
        background: rgba(31, 41, 55, 0.7);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
</style>

<div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- LEFT: Main Content -->
    <div class="lg:col-span-2 space-y-8">
        
        <!-- Hero Section -->
        <div class="glass p-8 rounded-2xl border border-gray-700 relative overflow-hidden">
            <!-- Background Icon -->
            <div class="absolute -right-6 -top-6 p-6 opacity-5 pointer-events-none">
                <i class="fas <?php echo htmlspecialchars($product['icon_class']); ?> text-9xl text-white"></i>
            </div>
            
            <div class="flex flex-col md:flex-row gap-6 relative z-10">
                <div class="w-24 h-24 bg-gradient-to-br from-gray-800 to-gray-900 rounded-2xl flex items-center justify-center text-4xl text-blue-500 shadow-xl border border-gray-700 shrink-0">
                    <i class="fas <?php echo htmlspecialchars($product['icon_class']); ?>"></i>
                </div>
                
                <div class="flex-1">
                    <div class="flex justify-between items-start">
                        <div>
                            <span class="text-xs font-bold text-blue-400 uppercase tracking-wider mb-1 block"><?php echo htmlspecialchars($product['cat_name']); ?></span>
                            <h1 class="text-3xl font-bold text-white mb-2"><?php echo htmlspecialchars($product['name']); ?></h1>
                            <div class="flex items-center gap-2 text-sm text-gray-400">
                                <div class="flex text-yellow-400 text-xs">
                                    <?php for($i=1; $i<=5; $i++) echo ($i <= $avg_rating) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star text-gray-600"></i>'; ?>
                                </div>
                                <span class="font-medium"><?php echo $avg_rating; ?></span>
                                <span class="text-gray-600">•</span>
                                <span><?php echo count($reviews); ?> Reviews</span>
                            </div>
                        </div>
                        
                        <!-- Wishlist Toggle -->
                        <?php if(is_logged_in()): ?>
                            <a href="index.php?module=shop&page=product&id=<?php echo $product['id']; ?>&wishlist=<?php echo $in_wishlist ? 'remove' : 'add'; ?>" 
                               class="w-10 h-10 rounded-full flex items-center justify-center border transition <?php echo $in_wishlist ? 'bg-red-500/10 border-red-500/50 text-red-500' : 'bg-gray-800 border-gray-600 text-gray-400 hover:text-white hover:border-gray-500'; ?>">
                                <i class="<?php echo $in_wishlist ? 'fas' : 'far'; ?> fa-heart"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="mt-6 p-5 bg-gray-800/50 rounded-xl border border-gray-700/50 text-gray-300 leading-relaxed text-sm">
                        <?php echo nl2br(htmlspecialchars($product['description'] ?? "Premium digital product. Instant delivery guaranteed.")); ?>
                    </div>
                    
                    <?php if($product['user_instruction']): ?>
                        <div class="mt-4 flex items-start gap-3 text-sm text-yellow-200/90 bg-yellow-900/20 p-3 rounded-lg border border-yellow-500/20">
                            <i class="fas fa-info-circle mt-0.5 text-yellow-500"></i>
                            <p><?php echo htmlspecialchars($product['user_instruction']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

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
                <form method="POST" class="mb-8 p-5 bg-gray-800/30 rounded-xl border border-gray-700/50">
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
                    <textarea name="comment" rows="2" placeholder="Share your experience..." required class="w-full bg-gray-900 border border-gray-600 rounded-lg p-3 text-white text-sm focus:border-blue-500 outline-none transition"></textarea>
                    <button type="submit" name="submit_review" class="mt-3 bg-blue-600 hover:bg-blue-500 text-white px-5 py-2 rounded-lg text-sm font-bold transition shadow-lg">Post Review</button>
                </form>
            <?php else: ?>
                <div class="mb-8 p-4 bg-gray-800/30 rounded-xl border border-gray-700 text-center text-sm text-gray-400">
                    Please <a href="index.php?module=auth&page=login" class="text-blue-400 hover:underline">Login</a> to leave a review.
                </div>
            <?php endif; ?>

            <!-- Review List -->
            <div class="space-y-6">
                <?php if(empty($reviews)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="far fa-comment-dots text-3xl mb-2 opacity-50"></i>
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
            <h3 class="text-gray-400 text-xs uppercase font-bold mb-4 tracking-widest">Order Summary</h3>
            
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

            <a href="index.php?module=shop&page=checkout&id=<?php echo $product['id']; ?>" class="block w-full bg-gradient-to-r from-green-600 to-green-500 hover:from-green-500 hover:to-green-400 text-white font-bold py-4 rounded-xl text-center shadow-lg transform hover:scale-[1.02] transition duration-200">
                Buy Now <i class="fas fa-arrow-right ml-2 text-sm"></i>
            </a>
            
            <div class="mt-4 grid grid-cols-2 gap-2 text-[10px] text-gray-500 font-medium text-center">
                <span class="bg-gray-800/50 py-1.5 rounded border border-gray-700/50"><i class="fas fa-bolt mr-1"></i> Instant</span>
                <span class="bg-gray-800/50 py-1.5 rounded border border-gray-700/50"><i class="fas fa-shield-alt mr-1"></i> Secure</span>
            </div>
        </div>

        <!-- Related Products -->
        <?php if(!empty($related)): ?>
        <div>
            <h4 class="font-bold text-white mb-4 text-sm uppercase tracking-wide">You may also like</h4>
            <div class="space-y-3">
                <?php foreach($related as $rel): ?>
                    <a href="index.php?module=shop&page=product&id=<?php echo $rel['id']; ?>" class="glass-card p-3 rounded-xl flex items-center gap-3 hover:bg-gray-800 transition group border border-transparent hover:border-gray-600">
                        <div class="w-12 h-12 bg-gray-900 rounded-lg flex items-center justify-center text-blue-500 text-lg border border-gray-700 group-hover:scale-105 transition">
                            <i class="fas <?php echo htmlspecialchars($rel['icon_class'] ?? 'fa-cube'); ?>"></i>
                        </div>
                        <div class="min-w-0">
                            <h5 class="text-sm font-bold text-gray-200 truncate group-hover:text-blue-400 transition"><?php echo htmlspecialchars($rel['name']); ?></h5>
                            <p class="text-xs text-gray-500 mt-0.5"><?php echo format_price($rel['price']); ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>