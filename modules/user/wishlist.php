<?php
// modules/user/wishlist.php

if (!is_logged_in()) redirect('index.php?module=auth&page=login');

// Handle Remove
if (isset($_GET['remove'])) {
    $pid = (int)$_GET['remove'];
    $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?")->execute([$_SESSION['user_id'], $pid]);
    redirect('index.php?module=user&page=wishlist');
}

// Fetch Wishlist
$stmt = $pdo->prepare("
    SELECT p.*, c.name as cat_name, c.icon_class 
    FROM wishlist w
    JOIN products p ON w.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    WHERE w.user_id = ?
    ORDER BY w.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$items = $stmt->fetchAll();

// Get Discount
$discount = get_user_discount($_SESSION['user_id']);
?>

<style>
    .glass-card {
        background: rgba(31, 41, 55, 0.7);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
</style>

<div class="max-w-7xl mx-auto">
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-white mb-2">My Wishlist</h1>
            <p class="text-gray-400 text-sm"><?php echo count($items); ?> items saved</p>
        </div>
        <a href="index.php" class="text-blue-400 hover:text-white text-sm transition">Continue Shopping &rarr;</a>
    </div>

    <?php if(empty($items)): ?>
        <div class="glass p-12 rounded-2xl text-center border border-gray-700/50">
            <div class="w-20 h-20 bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-6 shadow-inner">
                <i class="far fa-heart text-4xl text-gray-600"></i>
            </div>
            <h3 class="text-xl font-bold text-white mb-2">Your wishlist is empty</h3>
            <p class="text-gray-500 max-w-sm mx-auto mb-6">Save items you want to buy later by clicking the heart icon on any product.</p>
            <a href="index.php" class="bg-blue-600 hover:bg-blue-500 text-white px-6 py-2.5 rounded-lg font-bold transition shadow-lg inline-flex items-center gap-2">
                <i class="fas fa-store"></i> Browse Store
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach($items as $product): ?>
                <?php 
                    $base_price = $product['sale_price'] ?: $product['price'];
                    $final_price = $base_price * ((100 - $discount) / 100);
                ?>
                <div class="glass-card p-0 rounded-xl overflow-hidden group hover:border-blue-500/50 transition duration-300 relative flex flex-col h-full">
                    
                    <!-- Remove Button -->
                    <a href="index.php?module=user&page=wishlist&remove=<?php echo $product['id']; ?>" class="absolute top-3 right-3 w-8 h-8 bg-gray-900/80 rounded-full flex items-center justify-center text-red-400 hover:bg-red-600 hover:text-white transition z-20" title="Remove">
                        <i class="fas fa-times"></i>
                    </a>

                    <!-- Body -->
                    <div class="p-6 flex-grow flex flex-col">
                        <div class="flex items-start justify-between mb-4">
                            <div class="w-12 h-12 bg-gray-800 rounded-lg flex items-center justify-center text-2xl text-gray-400 group-hover:text-blue-400 group-hover:scale-110 transition border border-gray-700">
                                <i class="fas <?php echo htmlspecialchars($product['icon_class'] ?? 'fa-cube'); ?>"></i>
                            </div>
                            <span class="text-[10px] uppercase font-bold tracking-wider text-gray-500 bg-gray-800 px-2 py-1 rounded border border-gray-700 mr-8">
                                <?php echo htmlspecialchars($product['cat_name']); ?>
                            </span>
                        </div>

                        <h3 class="text-lg font-bold text-white mb-2 line-clamp-1 group-hover:text-blue-400 transition">
                            <a href="index.php?module=shop&page=product&id=<?php echo $product['id']; ?>">
                                <?php echo htmlspecialchars($product['name']); ?>
                            </a>
                        </h3>
                        
                        <!-- Price -->
                        <div class="mt-auto pt-4 border-t border-gray-700 flex items-center justify-between">
                            <div>
                                <p class="text-xs text-gray-500 mb-0.5">Price</p>
                                <div class="flex items-baseline gap-2">
                                    <?php if($product['sale_price'] || $discount > 0): ?>
                                        <span class="text-xs line-through text-gray-500"><?php echo format_price($product['price']); ?></span>
                                    <?php endif; ?>
                                    <span class="text-xl font-bold <?php echo ($discount > 0 || $product['sale_price']) ? 'text-yellow-400' : 'text-white'; ?>">
                                        <?php echo format_price($final_price); ?>
                                    </span>
                                </div>
                            </div>
                            <a href="index.php?module=shop&page=product&id=<?php echo $product['id']; ?>" 
                               class="bg-blue-600 hover:bg-blue-500 text-white w-10 h-10 rounded-full flex items-center justify-center shadow-lg transform group-hover:scale-110 transition" title="View Product">
                                <i class="fas fa-arrow-right text-sm"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>