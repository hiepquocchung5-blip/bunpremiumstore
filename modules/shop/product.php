<?php
// modules/shop/product.php
// PRODUCTION v4.0 - Fixed Math, Added Tabs, Stock Checking & Sharing

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
        $success = "Review submitted successfully!";
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
        } catch (Exception $e) {} // Ignore duplicates
    } else {
        $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?")->execute([$_SESSION['user_id'], $product_id]);
    }
    redirect("index.php?module=shop&page=product&id=$product_id");
}

// 2. Fetch Product Data
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

// ==========================================
// 5. Pricing & Discount Math Logic (FIXED)
// ==========================================
$discount = is_logged_in() ? get_user_discount($_SESSION['user_id']) : 0;

$original_price = $product['price'];
$sale_price = $product['sale_price'];

// Base price is sale price if it exists, otherwise original
$base_price = $sale_price ?: $original_price;
$sale_savings = $original_price - $base_price;

// Calculate Agent Savings
$agent_savings = $base_price * ($discount / 100);
$final_price = $base_price - $agent_savings;

// ==========================================
// 6. Stock Check Logic
// ==========================================
$can_buy = true;
$stock_status_html = '';

if ($product['delivery_type'] === 'unique') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_keys WHERE product_id = ? AND is_sold = 0");
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
?>

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

<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- LEFT: Main Content -->
        <div class="lg:col-span-2 space-y-8">
            
            <!-- Breadcrumb Navigation -->
            <div class="mb-2 flex items-center justify-between">
                <a href="index.php" class="inline-flex items-center gap-2 text-xs font-bold text-slate-500 hover:text-[#00f0ff] transition uppercase tracking-wider group">
                    <i class="fas fa-arrow-left group-hover:-translate-x-1 transition-transform"></i> Store Hub
                </a>
                
                <!-- Quick Actions (Share) -->
                <button onclick="shareProduct()" class="text-xs font-bold text-slate-500 hover:text-white transition uppercase tracking-wider flex items-center gap-2 bg-slate-800 px-3 py-1.5 rounded-lg border border-slate-700 hover:border-slate-500 shadow-sm">
                    <i class="fas fa-share-alt"></i> <span id="shareText">Share Node</span>
                </button>
            </div>

            <!-- Hero Section -->
            <div class="glass p-6 sm:p-8 rounded-3xl border border-[#00f0ff]/20 shadow-[0_20px_50px_rgba(0,0,0,0.5)] bg-slate-900/80 backdrop-blur-xl relative overflow-hidden group">
                
                <!-- Dynamic Background -->
                <div class="absolute inset-0 opacity-20 pointer-events-none bg-gradient-to-br from-blue-900 via-slate-900 to-slate-900"></div>
                <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PHBhdHRlcm4gaWQ9ImdyaWQiIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCIgcGF0dGVyblVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+PHBhdGggZD0iTSAwIDEwIEwgNDAgMTAgTSAxMCAwIEwgMTAgNDAiIGZpbGw9Im5vbmUiIHN0cm9rZT0icmdiYSgwLCAyNDAsIDI1NSwgMC4wNSkiIHN0cm9rZS13aWR0aD0iMSIvPjwvcGF0dGVybj48L2RlZnM+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0idXJsKCNncmlkKSIvPjwvc3ZnPg==')] opacity-30"></div>
                <div class="absolute -right-20 -top-20 w-64 h-64 bg-[#00f0ff]/10 rounded-full blur-3xl pointer-events-none"></div>

                <div class="flex flex-col md:flex-row gap-6 md:gap-8 relative z-10">
                    
                    <!-- Product Image Container -->
                    <div class="shrink-0 mx-auto md:mx-0 relative">
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
                        
                        <!-- Floating Wishlist Button -->
                        <?php if(is_logged_in()): ?>
                            <a href="index.php?module=shop&page=product&id=<?php echo $product['id']; ?>&wishlist=<?php echo $in_wishlist ? 'remove' : 'add'; ?>" 
                               class="absolute -top-3 -right-3 w-10 h-10 rounded-full flex items-center justify-center border transition-all duration-300 shadow-lg z-20 <?php echo $in_wishlist ? 'bg-rose-500 border-rose-400 text-white hover:bg-rose-600' : 'bg-slate-800 border-slate-600 text-slate-400 hover:text-white hover:border-slate-500 hover:bg-slate-700'; ?>"
                               title="<?php echo $in_wishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>">
                                <i class="<?php echo $in_wishlist ? 'fas' : 'far'; ?> fa-heart <?php echo $in_wishlist ? 'animate-pulse' : ''; ?>"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Product Info -->
                    <div class="flex-1 text-center md:text-left flex flex-col">
                        <div class="mb-4">
                            <div class="flex items-center justify-center md:justify-start gap-2 mb-3">
                                <span class="inline-block text-[9px] font-black text-[#00f0ff] bg-[#00f0ff]/10 px-2.5 py-1 rounded-md border border-[#00f0ff]/20 uppercase tracking-widest">
                                    <?php echo htmlspecialchars($product['cat_name']); ?>
                                </span>
                                <?php echo $stock_status_html; ?>
                            </div>
                            
                            <h1 class="text-3xl md:text-4xl font-black text-white mb-3 tracking-tight leading-tight"><?php echo htmlspecialchars($product['name']); ?></h1>
                            
                            <div class="flex items-center justify-center md:justify-start gap-3 text-sm text-slate-400 mt-2">
                                <div class="flex text-yellow-400 text-xs drop-shadow-[0_0_5px_rgba(234,179,8,0.5)]">
                                    <?php for($i=1; $i<=5; $i++) echo ($i <= $avg_rating) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star text-slate-600"></i>'; ?>
                                </div>
                                <span class="font-bold text-white"><?php echo $avg_rating; ?></span>
                                <span class="w-1.5 h-1.5 bg-slate-600 rounded-full"></span>
                                <span class="cursor-pointer hover:text-[#00f0ff] transition" onclick="switchTab('rev')"><?php echo count($reviews); ?> Reviews</span>
                            </div>
                        </div>

                        <!-- Delivery Features Banner -->
                        <div class="mt-auto grid grid-cols-2 gap-3">
                            <div class="bg-slate-900/60 p-3 rounded-xl border border-slate-700/50 flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-green-500/10 flex items-center justify-center text-green-400 shrink-0">
                                    <i class="fas <?php echo $product['delivery_type'] == 'unique' ? 'fa-bolt' : 'fa-clock'; ?>"></i>
                                </div>
                                <div class="text-left">
                                    <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">Delivery Time</p>
                                    <p class="text-xs text-white font-medium"><?php echo $product['delivery_type'] == 'unique' ? 'Instant Auto-Send' : '5-15 Mins (Manual)'; ?></p>
                                </div>
                            </div>
                            <div class="bg-slate-900/60 p-3 rounded-xl border border-slate-700/50 flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-purple-500/10 flex items-center justify-center text-purple-400 shrink-0">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <div class="text-left">
                                    <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">Warranty</p>
                                    <p class="text-xs text-white font-medium"><?php echo $product['duration_days'] ? $product['duration_days'].' Days Coverage' : 'Lifetime Valid'; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabbed Content Section -->
            <div class="glass rounded-3xl border border-slate-700 shadow-xl bg-slate-900/60 backdrop-blur-xl overflow-hidden">
                <!-- Tab Headers -->
                <div class="flex border-b border-slate-700/50 bg-slate-800/40 px-2 pt-2 overflow-x-auto no-scrollbar">
                    <button onclick="switchTab('desc')" id="btn-tab-desc" class="px-6 py-3.5 text-sm font-black uppercase tracking-wider text-[#00f0ff] border-b-2 border-[#00f0ff] transition-all whitespace-nowrap">
                        Overview
                    </button>
                    <button onclick="switchTab('inst')" id="btn-tab-inst" class="px-6 py-3.5 text-sm font-bold uppercase tracking-wider text-slate-400 border-b-2 border-transparent hover:text-white transition-all whitespace-nowrap">
                        Protocol Guide
                    </button>
                    <button onclick="switchTab('rev')" id="btn-tab-rev" class="px-6 py-3.5 text-sm font-bold uppercase tracking-wider text-slate-400 border-b-2 border-transparent hover:text-white transition-all whitespace-nowrap">
                        Comms (<?php echo count($reviews); ?>)
                    </button>
                </div>

                <!-- Tab Contents -->
                <div class="p-6 md:p-8">
                    
                    <!-- Description Tab -->
                    <div id="tab-desc" class="tab-content active text-slate-300 text-sm leading-relaxed space-y-4">
                        <?php echo nl2br(htmlspecialchars($product['description'] ?? "No extended data provided for this sector. Standard operational protocols apply.")); ?>
                    </div>

                    <!-- Instructions Tab -->
                    <div id="tab-inst" class="tab-content">
                        <?php if($product['user_instruction']): ?>
                            <div class="flex items-start gap-4 text-sm text-yellow-200/90 bg-gradient-to-r from-yellow-900/40 to-yellow-600/10 p-5 rounded-2xl border border-yellow-500/30 shadow-inner mb-6">
                                <div class="w-10 h-10 rounded-full bg-yellow-500/20 flex items-center justify-center shrink-0">
                                    <i class="fas fa-exclamation-triangle text-yellow-500 text-lg animate-pulse"></i>
                                </div>
                                <div class="pt-1">
                                    <h4 class="font-bold text-yellow-400 mb-1 uppercase tracking-wider text-xs">Crucial Directive</h4>
                                    <p class="font-medium leading-relaxed"><?php echo htmlspecialchars($product['user_instruction']); ?></p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8 text-slate-500">
                                <i class="fas fa-check-circle text-4xl mb-3 opacity-30 text-green-500"></i>
                                <p>Standard usage protocol. No special instructions required.</p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="bg-slate-800/50 rounded-xl p-5 border border-slate-700">
                            <h4 class="font-bold text-white mb-3 text-xs uppercase tracking-widest"><i class="fas fa-truck-loading text-blue-400 mr-2"></i> How you receive this</h4>
                            <p class="text-sm text-slate-400 leading-relaxed">
                                <?php 
                                    if($product['delivery_type'] == 'unique') echo "Upon payment verification, a unique digital key will be instantly dispatched to your secure Order Chat.";
                                    elseif($product['delivery_type'] == 'form') echo "You will provide necessary target details during checkout. An admin will process the injection manually.";
                                    else echo "Standard operational data will be delivered to your Order Chat post-verification.";
                                ?>
                            </p>
                        </div>
                    </div>

                    <!-- Reviews Tab -->
                    <div id="tab-rev" class="tab-content">
                        <?php if(isset($success)) echo "<div class='bg-green-500/10 text-green-400 p-4 rounded-xl mb-6 text-sm font-medium border border-green-500/30 flex items-center gap-3 shadow-lg'><i class='fas fa-shield-check text-lg'></i> $success</div>"; ?>
                        <?php if(isset($error)) echo "<div class='bg-red-500/10 text-red-400 p-4 rounded-xl mb-6 text-sm font-medium border border-red-500/30 flex items-center gap-3 shadow-lg'><i class='fas fa-exclamation-triangle text-lg'></i> $error</div>"; ?>

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

                        <div class="space-y-4">
                            <?php if(empty($reviews)): ?>
                                <div class="text-center py-8 text-slate-500">
                                    <i class="far fa-comment-dots text-4xl mb-3 opacity-20 text-[#00f0ff]"></i>
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
            </div>

            <!-- Related Products Grid -->
            <?php if(!empty($related_items)): ?>
                <div class="pt-4">
                    <h3 class="text-xl md:text-2xl font-black text-white mb-6 tracking-tight flex items-center gap-3">
                        <i class="fas fa-layer-group text-[#00f0ff]"></i> Similar Nodes
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
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
            <?php endif; ?>

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
                    
                    <!-- Original / Base Price -->
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-slate-400 font-medium">Retail Value</span>
                        <span class="font-mono <?php echo ($sale_savings > 0 || $discount > 0) ? 'text-slate-500 line-through decoration-slate-600' : 'text-white font-bold'; ?>">
                            <?php echo format_price($original_price); ?>
                        </span>
                    </div>
                    
                    <!-- Sale Savings -->
                    <?php if($sale_savings > 0): ?>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-red-400 font-bold flex items-center gap-1.5"><i class="fas fa-bolt text-[10px]"></i> Flash Sale</span>
                        <span class="text-red-400 font-mono font-bold">- <?php echo format_price($sale_savings); ?></span>
                    </div>
                    <?php endif; ?>

                    <!-- Agent Discount -->
                    <?php if($discount > 0): ?>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-yellow-400 font-bold flex items-center gap-1.5"><i class="fas fa-crown text-[10px]"></i> Agent Offset (-<?php echo $discount; ?>%)</span>
                        <span class="text-yellow-400 font-mono font-bold">- <?php echo format_price($agent_savings); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="flex justify-between items-end mb-8 relative z-10">
                    <span class="text-slate-300 font-black text-xs uppercase tracking-widest">Total Pay</span>
                    <span class="text-4xl font-black text-[#00f0ff] tracking-tighter drop-shadow-[0_0_15px_rgba(0,240,255,0.4)]"><?php echo format_price($final_price); ?></span>
                </div>

                <!-- Checkout Action -->
                <?php if($can_buy): ?>
                    <a href="index.php?module=shop&page=checkout&id=<?php echo $product['id']; ?>" class="block w-full bg-gradient-to-r from-blue-600 to-[#00f0ff] hover:from-blue-500 hover:to-[#00f0ff] text-slate-900 font-black py-4 rounded-xl text-center shadow-[0_0_20px_rgba(0,240,255,0.3)] hover:shadow-[0_0_30px_rgba(0,240,255,0.4)] transform hover:-translate-y-1 transition duration-300 uppercase tracking-widest flex items-center justify-center gap-2 group relative z-10">
                        <i class="fas fa-lock"></i> <span>Initiate Checkout</span>
                    </a>
                <?php else: ?>
                    <button disabled class="w-full bg-slate-800 border border-slate-700 text-slate-500 font-black py-4 rounded-xl text-center shadow-inner cursor-not-allowed uppercase tracking-widest flex items-center justify-center gap-2 relative z-10">
                        <i class="fas fa-ban"></i> <span>Out of Stock</span>
                    </button>
                    <p class="text-center text-[10px] text-red-400 mt-3 font-bold">This node is currently depleted. Check back later.</p>
                <?php endif; ?>
                
                <div class="mt-6 grid grid-cols-2 gap-3 text-[10px] uppercase font-bold tracking-widest text-center text-slate-500 relative z-10">
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
    // Tab System Logic
    function switchTab(tabId) {
        // Hide all contents
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        // Reset all buttons
        document.querySelectorAll('[id^="btn-tab-"]').forEach(el => {
            el.className = "px-6 py-3.5 text-sm font-bold uppercase tracking-wider text-slate-400 border-b-2 border-transparent hover:text-white transition-all whitespace-nowrap";
        });
        
        // Show target
        document.getElementById('tab-' + tabId).classList.add('active');
        // Highlight button
        document.getElementById('btn-tab-' + tabId).className = "px-6 py-3.5 text-sm font-black uppercase tracking-wider text-[#00f0ff] border-b-2 border-[#00f0ff] transition-all whitespace-nowrap";
    }

    // Share URL Logic
    function shareProduct() {
        const url = window.location.href;
        navigator.clipboard.writeText(url).then(() => {
            const btnText = document.getElementById('shareText');
            btnText.innerText = "Copied!";
            setTimeout(() => { btnText.innerText = "Share Node"; }, 2000);
        }).catch(err => {
            console.error('Failed to copy: ', err);
        });
    }

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