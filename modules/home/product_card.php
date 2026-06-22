<?php
// modules/home/product_card.php
// PRODUCTION DEPLOYMENT v4.8 - Refined UI/UX for Non-Tech Users (Failsafe Edition)

/**
 * Expects: 
 * $product (array from DB)
 * $discount (int - User's agent discount percentage)
 */

if (!isset($product) || !is_array($product)) {
    return;
}

// Calculate Final Price Safely
$base_price = (!empty($product['sale_price']) ? $product['sale_price'] : ($product['price'] ?? 0));
$discount = isset($discount) ? (int)$discount : 0;
$final_price = $base_price * ((100 - $discount) / 100);

// Determine Image/Icon to display
$has_product_image = !empty($product['image_path']);
$has_cat_image = !empty($product['cat_image']);
$fallback_icon = $product['icon_class'] ?? 'fa-cube'; 

// Generate Public Route
$product_url = product_public_url($product);

// Calculate Stock Count for Unique Delivery if not already set
if (isset($product['delivery_type']) && $product['delivery_type'] === 'unique' && !isset($product['stock_count']) && isset($pdo) && isset($product['id'])) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_keys WHERE product_id = ? AND is_sold = 0");
    $stmt->execute([$product['id']]);
    $product['stock_count'] = (int)$stmt->fetchColumn();
}
?>

<!-- Entire Card is a Unified Anchor Tag -->
<a href="<?php echo $product_url; ?>" class="dm-card block rounded-[1.75rem] overflow-hidden group transition-all duration-500 flex flex-col h-full hover:-translate-y-1.5 relative shadow-[0_12px_40px_rgba(0,0,0,0.18)]">

    <!-- Image / Icon Header -->
    <div class="prod-img-wrap">
        <?php if($has_product_image): ?>
            <img src="<?php echo BASE_URL . $product['image_path']; ?>" alt="<?php echo htmlspecialchars($product['name'] ?? 'Product Image'); ?>" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" loading="lazy">
        <?php elseif($has_cat_image): ?>
            <img src="<?php echo BASE_URL . $product['cat_image']; ?>" alt="Category" class="w-full h-full object-cover opacity-70 transition-transform duration-700 group-hover:scale-105" loading="lazy">
        <?php else: ?>
            <div class="w-full h-full flex items-center justify-center text-4xl text-slate-700">
                <i class="fas <?php echo htmlspecialchars($fallback_icon); ?>"></i>
            </div>
        <?php endif; ?>

        <!-- Badges -->
        <div class="absolute top-2.5 right-2.5 flex flex-col gap-1.5 items-end">
            <?php if(!empty($product['sale_price'])): ?>
                <span class="bg-rose-500 text-white text-[9px] font-bold px-2.5 py-0.5 rounded-full shadow-md uppercase tracking-widest">Sale</span>
            <?php endif; ?>
            <?php if($discount > 0): ?>
                <span class="bg-amber-500 text-black text-[9px] font-bold px-2.5 py-0.5 rounded-full shadow-md uppercase tracking-widest">-<?php echo $discount; ?>%</span>
            <?php endif; ?>
            <?php if(isset($product['delivery_type']) && $product['delivery_type'] === 'unique' && isset($product['stock_count'])): ?>
                <?php if($product['stock_count'] == 0): ?>
                    <span class="bg-red-600 text-white text-[9px] font-bold px-2.5 py-0.5 rounded-full shadow-md uppercase tracking-widest">
                        <i class="fas fa-times-circle mr-1"></i>Sold Out
                    </span>
                <?php elseif($product['stock_count'] < 5): ?>
                    <span class="bg-orange-500 text-white text-[9px] font-bold px-2.5 py-0.5 rounded-full shadow-md uppercase tracking-widest animate-pulse">
                        <i class="fas fa-fire mr-1"></i>Only <?php echo $product['stock_count']; ?> Left
                    </span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Card Body -->
    <div class="p-4 sm:p-5 flex-grow flex flex-col">
        <div class="flex items-center gap-2 mb-3">
            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.18em] truncate"><?php echo htmlspecialchars($product['cat_name'] ?? 'General'); ?></span>
        </div>

        <h3 class="text-white font-bold text-[15px] sm:text-lg mb-2 line-clamp-2 group-hover:text-blue-400 transition-colors leading-tight">
            <?php echo htmlspecialchars($product['name'] ?? 'Unnamed Product'); ?>
        </h3>
        
        <p class="text-slate-500 text-[11px] sm:text-xs line-clamp-2 mb-5 leading-relaxed">
            <?php echo htmlspecialchars(($product['user_instruction'] ?? '') ?: "Fast and secure delivery to your account."); ?>
        </p>
        
        <!-- Footer -->
        <div class="mt-auto pt-4 border-t border-white/5 flex items-center justify-between">
            <div class="flex flex-col">
                <span class="text-xl sm:text-2xl font-bold <?php echo (!empty($product['sale_price'])||$discount>0)?'text-amber-400':'text-white'; ?>">
                    <?php echo format_price($final_price); ?>
                </span>
                <?php if(!empty($product['sale_price']) || $discount > 0): ?>
                    <span class="text-[10px] text-slate-500 line-through font-medium">
                        <?php echo format_price($product['price'] ?? 0); ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <div class="w-9 h-9 rounded-full bg-slate-800/80 group-hover:bg-blue-600 flex items-center justify-center text-white transition-all transform group-hover:rotate-[-45deg]">
                <i class="fas fa-arrow-right text-xs"></i>
            </div>
        </div>
    </div>
</a>
