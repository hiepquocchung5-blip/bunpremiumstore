<?php
// modules/shop/search.php

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($query) {
    // Search in Products and Categories
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as cat_name 
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        WHERE p.name LIKE ? OR c.name LIKE ? 
        ORDER BY p.id DESC
    ");
    $search_term = "%$query%";
    $stmt->execute([$search_term, $search_term]);
    $results = $stmt->fetchAll();
} else {
    $results = [];
}

// Get Discount
$discount = is_logged_in() ? get_user_discount($_SESSION['user_id']) : 0;
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold mb-2">Search Results</h2>
    <p class="text-gray-400">Showing results for: <span class="text-white font-bold">"<?php echo htmlspecialchars($query); ?>"</span></p>
</div>

<?php if (empty($results) && !empty($query)): ?>
    <div class="glass p-10 rounded-xl text-center">
        <i class="fas fa-search text-4xl text-gray-600 mb-4"></i>
        <h3 class="text-xl font-bold text-gray-300">No products found.</h3>
        <p class="text-gray-500 mt-2">Try checking your spelling or use different keywords.</p>
        <a href="index.php" class="inline-block mt-4 bg-blue-600 hover:bg-blue-700 px-6 py-2 rounded font-bold text-white transition">Back to Home</a>
    </div>
<?php elseif (empty($query)): ?>
    <div class="text-center text-gray-500">Please enter a search term.</div>
<?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach($results as $product): ?>
            <?php 
                $final_price = $product['price'] * ((100 - $discount) / 100);
            ?>
            <div class="glass p-6 rounded-xl border border-gray-700 hover:border-blue-500 transition relative overflow-hidden group">
                <div class="flex justify-between items-start mb-2">
                    <h3 class="text-xl font-bold"><?php echo $product['name']; ?></h3>
                    <span class="text-xs bg-gray-800 px-2 py-1 rounded text-gray-400"><?php echo $product['cat_name']; ?></span>
                </div>
                
                <?php if($product['user_instruction']): ?>
                    <p class="text-xs text-gray-400 mb-4 bg-gray-800 p-2 rounded">
                        <i class="fas fa-info-circle text-blue-400"></i> <?php echo substr($product['user_instruction'], 0, 50) . '...'; ?>
                    </p>
                <?php endif; ?>

                <div class="flex justify-between items-end mt-4 pt-4 border-t border-gray-700">
                    <div>
                        <p class="text-xs text-gray-500">Price</p>
                        <div class="flex items-baseline gap-2">
                            <?php if($discount > 0): ?>
                                <span class="text-sm line-through text-gray-500"><?php echo format_price($product['price']); ?></span>
                                <span class="text-2xl font-bold text-yellow-400"><?php echo format_price($final_price); ?></span>
                            <?php else: ?>
                                <span class="text-2xl font-bold text-white"><?php echo format_price($product['price']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <a href="index.php?module=shop&page=checkout&id=<?php echo $product['id']; ?>" 
                       class="bg-blue-600 hover:bg-blue-700 px-6 py-2 rounded font-bold transition transform active:scale-95 shadow-lg">
                       Buy
                    </a>
                </div>
                
                <?php if($discount > 0): ?>
                    <div class="absolute top-0 right-0 bg-yellow-500 text-black text-[10px] font-bold px-2 py-1 rounded-bl-lg">
                        -<?php echo $discount; ?>% AGENT
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>