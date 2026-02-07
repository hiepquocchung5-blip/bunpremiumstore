<?php
// modules/shop/category.php

$cat_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch Category Details
$stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->execute([$cat_id]);
$category = $stmt->fetch();

if (!$category) die("Category not found");

// Fetch Products
$stmt = $pdo->prepare("SELECT * FROM products WHERE category_id = ?");
$stmt->execute([$cat_id]);
$products = $stmt->fetchAll();

// Get Discount
$discount = is_logged_in() ? get_user_discount($_SESSION['user_id']) : 0;
?>

<div class="mb-6">
    <a href="index.php" class="text-gray-400 hover:text-white flex items-center gap-2">
        <i class="fas fa-arrow-left"></i> Back to Store
    </a>
</div>

<div class="flex items-center gap-4 mb-8">
    <div class="w-16 h-16 bg-gray-800 rounded-xl flex items-center justify-center">
        <i class="fas <?php echo $category['icon_class']; ?> text-3xl text-blue-500"></i>
    </div>
    <div>
        <h2 class="text-3xl font-bold"><?php echo $category['name']; ?></h2>
        <p class="text-gray-400"><?php echo $category['description']; ?></p>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach($products as $product): ?>
        <?php 
            $final_price = $product['price'] * ((100 - $discount) / 100);
        ?>
        <div class="glass p-6 rounded-xl border border-gray-700 hover:border-blue-500 transition relative overflow-hidden group">
            <h3 class="text-xl font-bold mb-2"><?php echo $product['name']; ?></h3>
            
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