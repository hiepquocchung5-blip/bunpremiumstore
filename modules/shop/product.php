<?php
// modules/shop/product.php

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 1. Handle Review Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!is_logged_in()) redirect('index.php?module=auth&page=login');
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) die("Invalid Token");

    $rating = (int)$_POST['rating'];
    $comment = trim($_POST['comment']);

    // Check if user actually bought this product
    $hasBought = $pdo->prepare("SELECT id FROM orders WHERE user_id = ? AND product_id = ? AND status = 'active'");
    $hasBought->execute([$_SESSION['user_id'], $product_id]);

    if ($hasBought->rowCount() > 0) {
        // Check if already reviewed
        $hasReviewed = $pdo->prepare("SELECT id FROM reviews WHERE user_id = ? AND product_id = ?");
        $hasReviewed->execute([$_SESSION['user_id'], $product_id]);
        
        if ($hasReviewed->rowCount() == 0) {
            $stmt = $pdo->prepare("INSERT INTO reviews (user_id, product_id, rating, comment) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $product_id, $rating, $comment]);
            $success = "Review submitted successfully!";
        } else {
            $error = "You have already reviewed this product.";
        }
    } else {
        $error = "You must purchase this product to leave a review.";
    }
}

// 2. Handle Wishlist Toggle
if (isset($_GET['wishlist'])) {
    if (!is_logged_in()) redirect('index.php?module=auth&page=login');
    $action = $_GET['wishlist']; // 'add' or 'remove'
    
    if ($action == 'add') {
        try {
            $pdo->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)")->execute([$_SESSION['user_id'], $product_id]);
        } catch (Exception $e) {} // Ignore duplicate error
    } elseif ($action == 'remove') {
        $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?")->execute([$_SESSION['user_id'], $product_id]);
    }
    // Refresh to clear query param
    redirect("index.php?module=shop&page=product&id=$product_id");
}

// 3. Fetch Product Data
$stmt = $pdo->prepare("
    SELECT p.*, c.name as cat_name, c.icon_class 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    WHERE p.id = ?
");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    echo "<div class='p-10 text-center text-gray-500'>Product not found.</div>";
    return;
}

// 4. Fetch Reviews
$stmt = $pdo->prepare("SELECT r.*, u.username FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = ? ORDER BY r.created_at DESC");
$stmt->execute([$product_id]);
$reviews = $stmt->fetchAll();

// Calculate Average Rating
$avg_rating = 0;
if (count($reviews) > 0) {
    $total_rating = array_sum(array_column($reviews, 'rating'));
    $avg_rating = round($total_rating / count($reviews), 1);
}

// Check Wishlist Status
$in_wishlist = false;
if (is_logged_in()) {
    $check = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
    $check->execute([$_SESSION['user_id'], $product_id]);
    $in_wishlist = $check->rowCount() > 0;
}

// Price Calculation
$discount = is_logged_in() ? get_user_discount($_SESSION['user_id']) : 0;
$base_price = $product['sale_price'] ?: $product['price'];
$final_price = $base_price * ((100 - $discount) / 100);
?>

<div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- LEFT: Product Image & Details -->
    <div class="lg:col-span-2 space-y-8">
        
        <!-- Hero Card -->
        <div class="glass p-8 rounded-2xl border border-gray-700 relative overflow-hidden">
            <div class="absolute top-0 right-0 p-6 opacity-10">
                <i class="fas <?php echo htmlspecialchars($product['icon_class']); ?> text-9xl text-white"></i>
            </div>
            
            <div class="flex flex-col md:flex-row gap-6 relative z-10">
                <div class="w-24 h-24 bg-gray-800 rounded-2xl flex items-center justify-center text-4xl text-blue-500 shadow-lg border border-gray-600">
                    <i class="fas <?php echo htmlspecialchars($product['icon_class']); ?>"></i>
                </div>
                
                <div class="flex-1">
                    <div class="flex justify-between items-start">
                        <div>
                            <span class="text-xs font-bold text-blue-400 uppercase tracking-wider mb-1 block"><?php echo htmlspecialchars($product['cat_name']); ?></span>
                            <h1 class="text-3xl font-bold text-white mb-2"><?php echo htmlspecialchars($product['name']); ?></h1>
                            <div class="flex items-center gap-2 text-sm text-gray-400">
                                <div class="flex text-yellow-400">
                                    <?php for($i=1; $i<=5; $i++) echo ($i <= $avg_rating) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>'; ?>
                                </div>
                                <span>(<?php echo count($reviews); ?> Reviews)</span>
                            </div>
                        </div>
                        
                        <!-- Wishlist Button -->
                        <?php if(is_logged_in()): ?>
                            <a href="index.php?module=shop&page=product&id=<?php echo $product['id']; ?>&wishlist=<?php echo $in_wishlist ? 'remove' : 'add'; ?>" 
                               class="w-10 h-10 rounded-full flex items-center justify-center border transition <?php echo $in_wishlist ? 'bg-red-500/20 border-red-500 text-red-500' : 'bg-gray-800 border-gray-600 text-gray-400 hover:text-white'; ?>"
                               title="<?php echo $in_wishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>">
                                <i class="<?php echo $in_wishlist ? 'fas' : 'far'; ?> fa-heart"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="mt-6 p-4 bg-gray-800/50 rounded-xl border border-gray-700/50 text-gray-300 leading-relaxed text-sm">
                        <?php echo nl2br(htmlspecialchars($product['description'] ?? "No detailed description available.")); ?>
                    </div>
                    
                    <?php if($product['user_instruction']): ?>
                        <div class="mt-4 flex items-start gap-3 text-sm text-yellow-200/80 bg-yellow-900/10 p-3 rounded-lg border border-yellow-500/20">
                            <i class="fas fa-exclamation-triangle mt-0.5"></i>
                            <p><?php echo htmlspecialchars($product['user_instruction']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Reviews Section -->
        <div class="glass p-8 rounded-2xl border border-gray-700">
            <h3 class="text-xl font-bold text-white mb-6">Customer Reviews</h3>
            
            <?php if(isset($success)) echo "<div class='bg-green-500/20 text-green-400 p-3 rounded mb-4 text-sm'>$success</div>"; ?>
            <?php if(isset($error)) echo "<div class='bg-red-500/20 text-red-400 p-3 rounded mb-4 text-sm'>$error</div>"; ?>

            <!-- Review Form -->
            <?php if(is_logged_in()): ?>
                <form method="POST" class="mb-8 p-4 bg-gray-800/50 rounded-xl border border-gray-700/50">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <label class="block text-sm text-gray-400 mb-2">Write a review</label>
                    <div class="flex gap-2 mb-3">
                        <select name="rating" class="bg-gray-900 border border-gray-600 rounded px-3 py-1 text-sm text-white focus:border-blue-500 outline-none">
                            <option value="5">★★★★★ Excellent</option>
                            <option value="4">★★★★☆ Good</option>
                            <option value="3">★★★☆☆ Average</option>
                            <option value="2">★★☆☆☆ Poor</option>
                            <option value="1">★☆☆☆☆ Terrible</option>
                        </select>
                    </div>
                    <textarea name="comment" rows="2" placeholder="Share your experience..." required class="w-full bg-gray-900 border border-gray-600 rounded p-3 text-white text-sm focus:border-blue-500 outline-none"></textarea>
                    <button type="submit" name="submit_review" class="mt-3 bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded text-sm font-bold transition">Post Review</button>
                </form>
            <?php endif; ?>

            <!-- Reviews List -->
            <div class="space-y-4">
                <?php if(empty($reviews)): ?>
                    <p class="text-gray-500 text-center py-4">No reviews yet. Be the first!</p>
                <?php else: ?>
                    <?php foreach($reviews as $rev): ?>
                        <div class="border-b border-gray-700 pb-4 last:border-0 last:pb-0">
                            <div class="flex justify-between items-center mb-1">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-full bg-gray-700 flex items-center justify-center text-xs font-bold text-white">
                                        <?php echo strtoupper(substr($rev['username'], 0, 1)); ?>
                                    </div>
                                    <span class="text-sm font-bold text-gray-300"><?php echo htmlspecialchars($rev['username']); ?></span>
                                </div>
                                <span class="text-xs text-gray-500"><?php echo date('M d, Y', strtotime($rev['created_at'])); ?></span>
                            </div>
                            <div class="flex text-yellow-500 text-xs mb-2">
                                <?php for($i=1; $i<=5; $i++) echo ($i <= $rev['rating']) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>'; ?>
                            </div>
                            <p class="text-gray-400 text-sm"><?php echo htmlspecialchars($rev['comment']); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- RIGHT: Pricing & Action -->
    <div class="lg:col-span-1">
        <div class="glass p-6 rounded-2xl border border-gray-700 sticky top-24">
            <h3 class="text-gray-400 text-sm uppercase font-bold mb-4">Price Breakdown</h3>
            
            <div class="space-y-3 mb-6 border-b border-gray-700 pb-6">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-400">Regular Price</span>
                    <span class="text-white line-through decoration-gray-500"><?php echo format_price($product['price']); ?></span>
                </div>
                
                <?php if($product['sale_price']): ?>
                <div class="flex justify-between text-sm">
                    <span class="text-red-400">Sale Discount</span>
                    <span class="text-red-400 font-bold"><?php echo format_price($product['price'] - $product['sale_price']); ?> OFF</span>
                </div>
                <?php endif; ?>

                <?php if($discount > 0): ?>
                <div class="flex justify-between text-sm">
                    <span class="text-yellow-400 flex items-center gap-1"><i class="fas fa-crown text-xs"></i> Agent Discount</span>
                    <span class="text-yellow-400 font-bold"><?php echo $discount; ?>% OFF</span>
                </div>
                <?php endif; ?>
            </div>

            <div class="flex justify-between items-end mb-6">
                <span class="text-gray-300 font-bold">Total</span>
                <span class="text-3xl font-bold text-green-400"><?php echo format_price($final_price); ?></span>
            </div>

            <a href="index.php?module=shop&page=checkout&id=<?php echo $product['id']; ?>" class="block w-full bg-green-600 hover:bg-green-500 text-white font-bold py-4 rounded-xl text-center shadow-lg transform hover:scale-[1.02] transition duration-200">
                Buy Now
            </a>
            
            <div class="mt-4 flex items-center justify-center gap-4 text-xs text-gray-500">
                <span class="flex items-center gap-1"><i class="fas fa-bolt"></i> Instant</span>
                <span class="flex items-center gap-1"><i class="fas fa-shield-alt"></i> Secure</span>
            </div>
        </div>
    </div>

</div>