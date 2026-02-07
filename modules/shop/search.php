<?php
// modules/shop/search.php

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

// 1. Perform Search
if ($query) {
    $search_term = "%$query%";
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as cat_name, c.icon_class 
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        WHERE p.name LIKE ? OR c.name LIKE ? 
        ORDER BY p.id DESC
    ");
    $stmt->execute([$search_term, $search_term]);
    $results = $stmt->fetchAll();
} else {
    $results = [];
}

// 2. Get Agent Discount
$discount = is_logged_in() ? get_user_discount($_SESSION['user_id']) : 0;
?>

<style>
    .glass-card {
        background: rgba(31, 41, 55, 0.7);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
</style>

<div class="mb-8">
    <h2 class="text-3xl font-bold text-white mb-2">Search Results</h2>
    <?php if($query): ?>
        <p class="text-gray-400">Found <?php echo count($results); ?> results for <span class="text-white font-bold">"<?php echo htmlspecialchars($query); ?>"</span></p>
    <?php else: ?>
        <p class="text-gray-400">Please enter a keyword to search.</p>
    <?php endif; ?>
</div>

<?php if (empty($results) && !empty($query)): ?>
    <div class="glass p-12 rounded-2xl text-center border border-gray-700/50">
        <div class="w-20 h-20 bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-6 shadow-inner">
            <i class="fas fa-search text-4xl text-gray-600"></i>
        </div>
        <h3 class="text-xl font-bold text-white mb-2">No products found</h3>
        <p class="text-gray-500 max-w-sm mx-auto mb-6">We couldn't find any products matching your search. Try different keywords or browse our categories.</p>
        <a href="index.php" class="bg-blue-600 hover:bg-blue-500 text-white px-6 py-2.5 rounded-lg font-bold transition shadow-lg inline-flex items-center gap-2">
            <i class="fas fa-store"></i> Browse Store
        </a>
    </div>
<?php elseif (!empty($results)): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach($results as $product): ?>
            <?php 
                $base_price = $product['sale_price'] ?: $product['price'];
                $final_price = $base_price * ((100 - $discount) / 100);
            ?>
            <div class="glass-card p-0 rounded-xl overflow-hidden group hover:border-blue-500/50 transition duration-300 relative flex flex-col h-full">
                
                <!-- Badges -->
                <div class="absolute top-3 right-3 flex flex-col items-end gap-1 z-10">
                    <?php if($discount > 0): ?>
                        <span class="bg-yellow-500 text-black text-[10px] font-bold px-2 py-1 rounded shadow-lg">
                            -<?php echo $discount; ?>% AGENT
                        </span>
                    <?php endif; ?>
                    <?php if($product['sale_price']): ?>
                        <span class="bg-red-500 text-white text-[10px] font-bold px-2 py-1 rounded shadow-lg">SALE</span>
                    <?php endif; ?>
                </div>

                <!-- Product Body -->
                <div class="p-6 flex-grow flex flex-col">
                    <div class="flex items-start justify-between mb-4">
                        <div class="w-12 h-12 bg-gray-800 rounded-lg flex items-center justify-center text-2xl text-blue-500 group-hover:scale-110 transition border border-gray-700">
                            <i class="fas <?php echo htmlspecialchars($product['icon_class'] ?? 'fa-cube'); ?>"></i>
                        </div>
                        <span class="text-[10px] uppercase font-bold tracking-wider text-gray-500 bg-gray-800 px-2 py-1 rounded border border-gray-700">
                            <?php echo htmlspecialchars($product['cat_name']); ?>
                        </span>
                    </div>

                    <h3 class="text-lg font-bold text-white mb-2 line-clamp-2 group-hover:text-blue-400 transition">
                        <?php echo htmlspecialchars($product['name']); ?>
                    </h3>
                    
                    <?php if($product['user_instruction']): ?>
                        <p class="text-xs text-gray-400 line-clamp-2 mb-4 bg-gray-800/50 p-2 rounded border border-gray-700/50">
                            <i class="fas fa-info-circle mr-1 text-blue-400"></i> <?php echo htmlspecialchars($product['user_instruction']); ?>
                        </p>
                    <?php else: ?>
                        <p class="text-xs text-gray-500 mb-4 h-8">Instant delivery available.</p>
                    <?php endif; ?>

                    <!-- Price & Action -->
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
                        <a href="index.php?module=shop&page=checkout&id=<?php echo $product['id']; ?>" 
                           class="bg-blue-600 hover:bg-blue-500 text-white w-10 h-10 rounded-full flex items-center justify-center shadow-lg transform group-hover:scale-110 transition">
                            <i class="fas fa-shopping-cart text-sm"></i>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>