<?php
// modules/user/wishlist.php
// PRODUCTION v2.0 - Hardened Schema & Humanized UI

if (!is_logged_in()) redirect('index.php?module=auth&page=login');

$user_id = $_SESSION['user_id'];
$items = [];
$error_sync = false;

// 1. Handle Remove Action
if (isset($_GET['remove'])) {
    $pid = (int)$_GET['remove'];
    try {
        $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?")->execute([$user_id, $pid]);
        redirect('index.php?module=user&page=wishlist');
    } catch (Exception $e) {
        // Fail silently
    }
}

// 2. Fetch Wishlist Data (Hardened Query)
try {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as cat_name, c.image_url as cat_image
        FROM wishlist w
        JOIN products p ON w.product_id = p.id
        JOIN categories c ON p.category_id = c.id
        WHERE w.user_id = ?
        ORDER BY w.id DESC
    ");
    $stmt->execute([$user_id]);
    $items = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Wishlist Sync Error: " . $e->getMessage());
    $error_sync = true;
}

// 3. Get Discount Protocols
$discount = get_user_discount($user_id);
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 animate-fade-in-down">
    
    <!-- Header -->
    <div class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-6">
        <div>
            <div class="flex items-center gap-3 mb-2">
                <span class="text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] bg-slate-900/50 px-2.5 py-1 rounded-md border border-slate-800">Saved Items</span>
                <div class="h-px bg-slate-800 w-12"></div>
            </div>
            <h1 class="text-3xl md:text-5xl font-black text-white tracking-tight">My Wishlist</h1>
            <p class="text-slate-400 text-sm mt-3 font-medium">You have <?php echo count($items); ?> products saved for later.</p>
        </div>
        <a href="index.php" class="text-blue-400 hover:text-[#00f0ff] text-sm font-black flex items-center gap-2 transition-all uppercase tracking-widest bg-blue-500/10 hover:bg-[#00f0ff]/10 px-5 py-3 rounded-xl border border-blue-500/20">
            <i class="fas fa-store"></i> Back to Store
        </a>
    </div>

    <?php if($error_sync): ?>
        <!-- Nice Error State -->
        <div class="bg-red-900/10 border border-red-500/30 p-12 rounded-3xl text-center shadow-2xl">
            <i class="fas fa-satellite-dish text-4xl text-red-500 mb-4 animate-pulse"></i>
            <h3 class="text-xl font-bold text-white mb-2 uppercase tracking-tight">Syncing with Store...</h3>
            <p class="text-slate-400 text-sm max-w-xs mx-auto">We're having a small trouble loading your saved items. Please try again in a few moments.</p>
        </div>
    <?php elseif(empty($items)): ?>
        <!-- Empty State -->
        <div class="bg-slate-900/60 backdrop-blur-xl border border-slate-700/50 p-16 rounded-3xl text-center shadow-2xl relative overflow-hidden group">
            <div class="absolute -right-20 -top-20 w-64 h-64 bg-blue-600/5 rounded-full blur-3xl pointer-events-none group-hover:bg-blue-600/10 transition-colors"></div>
            <div class="w-24 h-24 bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-8 shadow-inner border border-slate-700">
                <i class="far fa-heart text-5xl text-slate-600"></i>
            </div>
            <h3 class="text-2xl font-black text-white mb-3 uppercase tracking-tight">Your wishlist is empty</h3>
            <p class="text-slate-500 max-w-sm mx-auto mb-10 font-medium leading-relaxed">See something you like? Click the heart icon on any product to save it here.</p>
            <a href="index.php" class="bg-blue-600 hover:bg-blue-500 text-white px-10 py-4 rounded-2xl font-black transition shadow-[0_0_30px_rgba(37,99,235,0.3)] inline-flex items-center gap-3 uppercase tracking-widest text-xs">
                Explore Products <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    <?php else: ?>
        <!-- Items Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach($items as $product): ?>
                <?php 
                    $base_price = $product['sale_price'] ?: $product['price'];
                    $final_price = $base_price * ((100 - $discount) / 100);
                ?>
                <div class="glass-card p-0 rounded-3xl overflow-hidden group hover:border-[#00f0ff]/50 transition-all duration-500 relative flex flex-col h-full bg-slate-900/80 border border-slate-700/50 shadow-2xl">
                    
                    <!-- Top Actions -->
                    <div class="absolute top-4 right-4 z-20 flex gap-2">
                        <a href="index.php?module=user&page=wishlist&remove=<?php echo $product['id']; ?>" 
                           class="w-10 h-10 bg-slate-950/80 backdrop-blur-md border border-slate-700 rounded-xl flex items-center justify-center text-red-400 hover:bg-red-500 hover:text-white transition shadow-lg" 
                           title="Remove Item">
                            <i class="fas fa-trash-alt text-sm"></i>
                        </a>
                    </div>

                    <!-- Visual Section -->
                    <div class="aspect-video relative overflow-hidden bg-slate-800 border-b border-slate-700/50">
                        <?php if(!empty($product['image_path'])): ?>
                            <img src="<?php echo BASE_URL . $product['image_path']; ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700">
                        <?php else: ?>
                            <img src="<?php echo BASE_URL . $product['cat_image']; ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700 opacity-60">
                            <div class="absolute inset-0 flex items-center justify-center text-4xl text-blue-500/30">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                        <?php endif; ?>
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-transparent to-transparent opacity-60"></div>
                        
                        <!-- Category Badge -->
                        <span class="absolute bottom-4 left-4 px-3 py-1 bg-blue-600/90 backdrop-blur-md text-[10px] font-black text-white uppercase tracking-widest rounded-lg shadow-lg">
                            <?php echo htmlspecialchars($product['cat_name']); ?>
                        </span>
                    </div>

                    <!-- Content -->
                    <div class="p-6 flex-grow flex flex-col">
                        <h3 class="text-xl font-black text-white mb-4 line-clamp-2 group-hover:text-[#00f0ff] transition-colors leading-tight">
                            <a href="index.php?module=shop&page=product&id=<?php echo $product['id']; ?>">
                                <?php echo htmlspecialchars($product['name']); ?>
                            </a>
                        </h3>
                        
                        <!-- Footer / Price -->
                        <div class="mt-auto pt-6 border-t border-slate-800 flex items-center justify-between">
                            <div>
                                <div class="flex items-baseline gap-2">
                                    <?php if($product['sale_price'] || $discount > 0): ?>
                                        <span class="text-[10px] line-through text-slate-500 font-mono"><?php echo format_price($product['price']); ?></span>
                                    <?php endif; ?>
                                    <span class="text-2xl font-black <?php echo ($discount > 0 || $product['sale_price']) ? 'text-yellow-400' : 'text-white'; ?> tracking-tighter">
                                        <?php echo format_price($final_price); ?>
                                    </span>
                                </div>
                                <p class="text-[9px] text-slate-500 font-bold uppercase tracking-widest mt-1">Instant Delivery</p>
                            </div>
                            
                            <a href="index.php?module=shop&page=product&id=<?php echo $product['id']; ?>" 
                               class="bg-blue-600 hover:bg-[#00f0ff] text-white hover:text-slate-900 w-12 h-12 rounded-2xl flex items-center justify-center shadow-lg transform hover:scale-110 active:scale-95 transition-all duration-300" 
                               title="View Product">
                                <i class="fas fa-shopping-cart"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
    .glass-card {
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(255, 255, 255, 0.05);
    }
</style>