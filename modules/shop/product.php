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
// FIX: Selected c.image_url instead of c.icon_class
$stmt = $pdo->prepare("
    SELECT p.*, c.name as cat_name, c.image_url as cat_image
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    WHERE p.id = ?
");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    echo "<div class='p-20 text-center text-slate-500'>Product not found. <a href='index.php' class='text-[#00f0ff] hover:underline'>Return to Hub</a></div>";
    return;
}

// 3. Fetch Related Products
// FIX: Selected c.image_url instead of c.icon_class
$stmt = $pdo->prepare("
    SELECT p.*, c.name as cat_name, c.image_url as cat_image
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

$has_cat_image = !empty($product['cat_image']);
?>

<style>
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
        background-color: rgba(15, 23, 42, 0.95);
        backdrop-filter: blur(10px);
    }
    .lightbox-content {
        margin: auto;
        display: block;
        width: 90%;
        max-width: 800px;
        border-radius: 16px;
        box-shadow: 0 0 50px rgba(0, 240, 255, 0.3);
        border: 1px solid rgba(0, 240, 255, 0.2);
    }
    .close-lightbox {
        position: absolute;
        top: 30px;
        right: 35px;
        color: #94a3b8;
        font-size: 40px;
        font-weight: bold;
        transition: 0.3s;
        cursor: pointer;
    }
    .close-lightbox:hover { color: #fff; }
</style>

<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- LEFT: Main Content -->
        <div class="lg:col-span-2 space-y-8">
            
            <!-- Navigation Back -->
            <div class="mb-2">
                <a href="index.php" class="inline-flex items-center gap-2 text-xs font-bold text-slate-500 hover:text-[#00f0ff] transition uppercase tracking-wider group">
                    <i class="fas fa-arrow-left group-hover:-translate-x-1 transition-transform"></i> Store Hub
                </a>
            </div>

            <!-- Hero Section -->
            <div class="glass p-6 sm:p-8 rounded-3xl border border-[#00f0ff]/20 shadow-[0_20px_50px_rgba(0,0,0,0.5)] bg-slate-900/80 backdrop-blur-xl relative overflow-hidden group">
                
                <!-- Dynamic Background -->
                <div class="absolute inset-0 opacity-20 pointer-events-none bg-gradient-to-br from-blue-900 via-slate-900 to-slate-900"></div>
                <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PHBhdHRlcm4gaWQ9ImdyaWQiIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCIgcGF0dGVyblVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+PHBhdGggZD0iTSAwIDEwIEwgNDAgMTAgTSAxMCAwIEwgMTAgNDAiIGZpbGw9Im5vbmUiIHN0cm9rZT0icmdiYSgwLCAyNDAsIDI1NSwgMC4wNSkiIHN0cm9rZS13aWR0aD0iMSIvPjwvcGF0dGVybj48L2RlZnM+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0idXJsKCNncmlkKSIvPjwvc3ZnPg==')] opacity-30"></div>
                
                <div class="absolute -right-20 -top-20 w-64 h-64 bg-[#00f0ff]/10 rounded-full blur-3xl pointer-events-none"></div>

                <div class="flex flex-col md:flex-row gap-6 md:gap-8 relative z-10">
                    <!-- Product Image/Icon Container -->
                    <div class="shrink-0 mx-auto md:mx-0">
                        <?php if($has_image): ?>
                            <div class="w-48 h-48 md:w-56 md:h-56 rounded-2xl overflow-hidden shadow-[0_0_30px_rgba(0,240,255,0.2)] border border-[#00f0ff]/30 relative cursor-zoom-in hover:scale-105 transition-all duration-500" onclick="openLightbox(this.querySelector('img').src)">
                                <img src="<?php echo $display_image; ?>" class="w-full h-full object-cover">
                                <div class="absolute inset-0 bg-black/10 hover:bg-transparent transition duration-300"></div>
                            </div>
                        <?php elseif($has_cat_image): ?>
                            <div class="w-48 h-48 md:w-56 md:h-56 rounded-2xl overflow-hidden shadow-[0_0_30px_rgba(0,240,255,0.2)] border border-[#00f0ff]/30 relative cursor-zoom-in hover:scale-105 transition-all duration-500" onclick="openLightbox(this.querySelector('img').src)">
                                <img src="<?php echo BASE_URL . $product['cat_image']; ?>" class="w-full h-full object-cover">
                                <div class="absolute inset-0 bg-black/10 hover:bg-transparent transition duration-300"></div>
                            </div>
                        <?php else: ?>
                            <div class="w-32 h-32 md:w-48 md:h-48 bg-slate-900 rounded-2xl flex items-center justify-center text-6xl text-[#00f0ff] shadow-[0_0_20px_rgba(0,240,255,0.1)] border border-slate-700 relative overflow-hidden">
                                <div class="absolute inset-0 bg-gradient-to-br from-blue-600/20 to-[#00f0ff]/20"></div>
                                <i class="fas fa-cube relative z-10 drop-shadow-[0_0_10px_rgba(0,240,255,0.5)]"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex-1 text-center md:text-left flex flex-col">
                        <div class="flex flex-col md:flex-row justify-between items-center md:items-start gap-4 mb-4">
                            <div>
                                <span class="inline-block text-[10px] font-black text-[#00f0ff] bg-[#00f0ff]/10 px-2.5 py-1 rounded-md border border-[#00f0ff]/20 uppercase tracking-widest mb-3">
                                    <?php echo htmlspecialchars($product['cat_name']); ?>
                                </span>
                                <h1 class="text-3xl md:text-4xl font-black text-white mb-3 tracking-tight leading-tight"><?php echo htmlspecialchars($product['name']); ?></h1>
                                
                                <div class="flex items-center justify-center md:justify-start gap-3 text-sm text-slate-400">
                                    <div class="flex text-yellow-400 text-xs drop-shadow-[0_0_5px_rgba(234,179,8,0.5)]">
                                        <?php for($i=1; $i<=5; $i++) echo ($i <= $avg_rating) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star text-slate-600"></i>'; ?>
                                    </div>
                                    <span class="font-bold text-white"><?php echo $avg_rating; ?></span>
                                    <span class="w-1.5 h-1.5 bg-slate-600 rounded-full"></span>
                                    <span><?php echo count($reviews); ?> Reviews</span>
                                </div>
                            </div>
                            
                            <!-- Wishlist Toggle -->
                            <?php if(is_logged_in()): ?>
                                <a href="index.php?module=shop&page=product&id=<?php echo $product['id']; ?>&wishlist=<?php echo $in_wishlist ? 'remove' : 'add'; ?>" 
                                   class="w-12 h-12 rounded-xl flex items-center justify-center border transition-all duration-300 shadow-lg shrink-0 <?php echo $in_wishlist ? 'bg-rose-500/10 border-rose-500/50 text-rose-500 hover:bg-rose-500/20' : 'bg-slate-800 border-slate-600 text-slate-400 hover:text-white hover:border-slate-500 hover:bg-slate-700'; ?>"
                                   title="<?php echo $in_wishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>">
                                    <i class="<?php echo $in_wishlist ? 'fas' : 'far'; ?> fa-heart text-xl <?php echo $in_wishlist ? 'animate-pulse' : ''; ?>"></i>
                                </a>
                            <?php endif; ?>
                        </div>

                        <div class="mt-auto bg-slate-900/60 rounded-2xl border border-slate-700/50 p-5 text-slate-300 text-sm leading-relaxed text-left shadow-inner">
                            <?php echo nl2br(htmlspecialchars($product['description'] ?? "Premium digital product. Secure delivery guaranteed via automated matrix.")); ?>
                        </div>
                        
                        <?php if($product['user_instruction']): ?>
                            <div class="mt-4 flex items-start gap-3 text-sm text-yellow-200/90 bg-gradient-to-r from-yellow-900/40 to-yellow-600/10 p-4 rounded-xl border border-yellow-500/30 text-left shadow-lg">
                                <i class="fas fa-exclamation-triangle mt-0.5 text-yellow-500 shrink-0 animate-pulse"></i>
                                <p class="font-medium"><?php echo htmlspecialchars($product['user_instruction']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Related Products -->
            <?php if(!empty($related_items)): ?>
                <div>
                    <h3 class="text-xl md:text-2xl font-black text-white mb-6 tracking-tight flex items-center gap-3">
                        <i class="fas fa-layer-group text-[#00f0ff]"></i> Similar Nodes
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
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
            <div class="glass p-6 sm:p-8 rounded-3xl border border-slate-700 shadow-2xl bg-slate-900/60 backdrop-blur-xl">
                <div class="flex items-center justify-between mb-8 border-b border-slate-700/50 pb-4">
                    <h3 class="text-xl md:text-2xl font-black text-white flex items-center gap-3">
                        <i class="fas fa-comments text-[#00f0ff]"></i> Network Comms
                    </h3>
                    <span class="text-xs font-bold text-slate-400 bg-slate-800 px-3 py-1.5 rounded-lg border border-slate-700 uppercase tracking-widest">
                        <?php echo count($reviews); ?> Logs
                    </span>
                </div>
                
                <?php if(isset($success)) echo "<div class='bg-green-500/10 text-green-400 p-4 rounded-xl mb-6 text-sm font-medium border border-green-500/30 flex items-center gap-3 shadow-lg'><i class='fas fa-shield-check text-lg'></i> $success</div>"; ?>
                <?php if(isset($error)) echo "<div class='bg-red-500/10 text-red-400 p-4 rounded-xl mb-6 text-sm font-medium border border-red-500/30 flex items-center gap-3 shadow-lg'><i class='fas fa-exclamation-triangle text-lg'></i> $error</div>"; ?>

                <!-- Write Review -->
                <?php if(is_logged_in()): ?>
                    <form method="POST" class="mb-10 p-6 bg-slate-800/50 rounded-2xl border border-slate-700 transition focus-within:border-[#00f0ff]/50 shadow-inner">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
                            <span class="text-sm font-bold text-slate-300 uppercase tracking-wider">Evaluate Sector</span>
                            <div class="flex flex-row-reverse justify-end gap-2 group">
                                <?php for($i=5; $i>=1; $i--): ?>
                                    <input type="radio" name="rating" value="<?php echo $i; ?>" id="star<?php echo $i; ?>" class="peer hidden" required>
                                    <label for="star<?php echo $i; ?>" class="cursor-pointer text-slate-600 peer-checked:text-yellow-400 hover:text-yellow-400 peer-hover:text-yellow-400 transition-colors text-2xl drop-shadow-md"><i class="fas fa-star"></i></label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <textarea name="comment" rows="3" placeholder="Transmit your experience..." required class="w-full bg-slate-900/80 border border-slate-600 rounded-xl p-4 text-white text-sm focus:border-[#00f0ff] focus:ring-1 focus:ring-[#00f0ff] outline-none transition-all placeholder-slate-500 resize-none shadow-inner"></textarea>
                        <div class="flex justify-end mt-4">
                            <button type="submit" name="submit_review" class="bg-gradient-to-r from-blue-600 to-[#00f0ff] hover:from-blue-500 hover:to-[#00f0ff] text-slate-900 px-6 py-3 rounded-xl text-sm font-black transition-all shadow-[0_0_15px_rgba(0,240,255,0.3)] transform active:scale-95 flex items-center gap-2 uppercase tracking-widest">
                                <span>Transmit</span> <i class="fas fa-satellite-dish"></i>
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="mb-10 p-8 bg-slate-800/30 rounded-2xl border border-slate-700 border-dashed text-center">
                        <i class="fas fa-lock text-3xl text-slate-600 mb-3"></i>
                        <p class="text-slate-400 text-sm mb-4">Authentication required to transmit feedback.</p>
                        <a href="index.php?module=auth&page=login" class="bg-slate-700 hover:bg-slate-600 text-white font-bold py-2.5 px-6 rounded-xl transition text-sm shadow-md inline-block">Initialize Login</a>
                    </div>
                <?php endif; ?>

                <!-- Review List -->
                <div class="space-y-6">
                    <?php if(empty($reviews)): ?>
                        <div class="text-center py-12 text-slate-500">
                            <i class="far fa-comment-dots text-5xl mb-4 opacity-20 text-[#00f0ff]"></i>
                            <p class="text-sm font-medium">No comms intercepted yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($reviews as $rev): ?>
                            <div class="p-5 rounded-2xl bg-slate-800/30 border border-slate-700/50 hover:border-slate-600 transition-colors">
                                <div class="flex justify-between items-start mb-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-slate-800 flex items-center justify-center text-sm font-bold text-white shadow-inner border border-slate-700">
                                            <?php echo strtoupper(substr($rev['username'], 0, 2)); ?>
                                        </div>
                                        <div>
                                            <span class="text-sm font-bold text-slate-200">@<?php echo htmlspecialchars($rev['username']); ?></span>
                                            <div class="flex text-yellow-400 text-[10px] mt-0.5">
                                                <?php for($i=1; $i<=5; $i++) echo ($i <= $rev['rating']) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star text-slate-600"></i>'; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <span class="text-[10px] font-mono text-slate-500"><?php echo date('Y.m.d', strtotime($rev['created_at'])); ?></span>
                                </div>
                                <p class="text-slate-400 text-sm leading-relaxed bg-slate-900/50 p-3 rounded-xl border border-slate-700/30 font-medium">
                                    "<?php echo htmlspecialchars($rev['comment']); ?>"
                                </p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- RIGHT: Pricing & Upsell (Sticky Sidebar) -->
        <div class="lg:col-span-1">
            
            <div class="glass p-6 md:p-8 rounded-3xl border border-slate-700 shadow-[0_20px_40px_rgba(0,0,0,0.4)] sticky top-24 bg-slate-900/80 backdrop-blur-xl relative overflow-hidden">
                <!-- Decorative Glow -->
                <div class="absolute -right-10 -top-10 w-32 h-32 bg-[#00f0ff]/10 rounded-full blur-3xl pointer-events-none"></div>

                <h3 class="text-slate-400 text-xs uppercase font-black mb-6 tracking-widest flex items-center gap-2 border-b border-slate-700/50 pb-4">
                    <i class="fas fa-receipt text-[#00f0ff]"></i> Acquisition Summary
                </h3>
                
                <div class="space-y-4 mb-8 border-b border-slate-700/50 pb-6">
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-slate-400 font-medium">Base Value</span>
                        <span class="font-mono <?php echo ($discount > 0 || $product['sale_price']) ? 'text-slate-500 line-through decoration-slate-600' : 'text-white font-bold'; ?>">
                            <?php echo format_price($product['price']); ?>
                        </span>
                    </div>
                    
                    <?php if($product['sale_price']): ?>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-red-400 font-bold flex items-center gap-1.5"><i class="fas fa-bolt text-[10px]"></i> Flash Sale</span>
                        <span class="text-red-400 font-mono font-bold">- <?php echo format_price($product['price'] - $product['sale_price']); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if($discount > 0): ?>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-yellow-400 font-bold flex items-center gap-1.5"><i class="fas fa-crown text-[10px]"></i> Agent Offset</span>
                        <span class="text-yellow-400 font-mono font-bold">- <?php echo $discount; ?>%</span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="flex justify-between items-end mb-8">
                    <span class="text-slate-300 font-black text-xs uppercase tracking-widest">Total Pay</span>
                    <span class="text-4xl font-black text-[#00f0ff] tracking-tighter drop-shadow-[0_0_15px_rgba(0,240,255,0.4)]"><?php echo format_price($final_price); ?></span>
                </div>

                <a href="index.php?module=shop&page=checkout&id=<?php echo $product['id']; ?>" class="block w-full bg-gradient-to-r from-blue-600 to-[#00f0ff] hover:from-blue-500 hover:to-[#00f0ff] text-slate-900 font-black py-4 rounded-xl text-center shadow-[0_0_20px_rgba(0,240,255,0.3)] hover:shadow-[0_0_30px_rgba(0,240,255,0.4)] transform hover:-translate-y-1 transition duration-300 uppercase tracking-widest flex items-center justify-center gap-2 group">
                    <i class="fas fa-lock"></i> <span>Initiate Checkout</span>
                </a>
                
                <div class="mt-6 grid grid-cols-2 gap-3 text-[10px] uppercase font-bold tracking-widest text-center text-slate-500">
                    <div class="bg-slate-800/50 py-2.5 rounded-lg border border-slate-700 flex flex-col items-center gap-1">
                        <i class="fas fa-rocket text-yellow-400 text-sm"></i>
                        <span>Fast Delivery</span>
                    </div>
                    <div class="bg-slate-800/50 py-2.5 rounded-lg border border-slate-700 flex flex-col items-center gap-1">
                        <i class="fas fa-shield-check text-green-400 text-sm"></i>
                        <span>Secure Node</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Lightbox Element -->
<div id="productLightbox" class="lightbox flex" onclick="closeLightbox()" style="display:none;">
    <span class="close-lightbox">&times;</span>
    <img class="lightbox-content transform scale-95 transition-transform duration-300" id="lightboxImg">
</div>

<script>
    function openLightbox(src) {
        const lightbox = document.getElementById('productLightbox');
        const img = document.getElementById('lightboxImg');
        img.src = src;
        lightbox.style.display = "flex";
        // Animate in
        setTimeout(() => img.classList.remove('scale-95'), 10);
        // Prevent body scroll
        document.body.style.overflow = "hidden";
    }
    
    function closeLightbox() {
        const lightbox = document.getElementById('productLightbox');
        const img = document.getElementById('lightboxImg');
        // Animate out
        img.classList.add('scale-95');
        setTimeout(() => {
            lightbox.style.display = "none";
            document.body.style.overflow = "auto";
        }, 300);
    }
</script>