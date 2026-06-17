<?php
// modules/home/product_card.php
// PRODUCTION DEPLOYMENT v4.7 - Refined UI/UX for Non-Tech Users (Circuit Chaos Edition)

/**
 * Expects: 
 * $product (array from DB)
 * $discount (int - User's agent discount percentage)
 */

// Calculate Final Price
$base_price = $product['sale_price'] ?: $product['price'];
$final_price = $base_price * ((100 - $discount) / 100);

// Determine Image/Icon to display
$has_product_image = !empty($product['image_path']);
$has_cat_image = !empty($product['cat_image']);
$fallback_icon = $product['icon_class'] ?? 'fa-cube'; 

// Generate Secure Route
$product_url = "index.php?module=shop&page=product&id=" . $product['id'];
?>

<!-- Entire Card is a Unified Anchor Tag -->
<a href="<?php echo $product_url; ?>" class="block rounded-[2rem] overflow-hidden group hover:bg-slate-800/50 transition-all duration-500 flex flex-col h-full bg-slate-800/20 border border-white/5 hover:border-blue-500/30 shadow-xl hover:-translate-y-2 relative">
    
    <!-- Image / Icon Header -->
    <div class="relative aspect-square overflow-hidden bg-slate-900">
        <?php if($has_product_image): ?>
            <img src="<?php echo BASE_URL . $product['image_path']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110" loading="lazy">
        <?php elseif($has_cat_image): ?>
            <img src="<?php echo BASE_URL . $product['cat_image']; ?>" alt="Category" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110" loading="lazy">
        <?php else: ?>
            <div class="w-full h-full flex items-center justify-center text-4xl text-slate-700">
                <i class="fas <?php echo htmlspecialchars($fallback_icon); ?>"></i>
            </div>
        <?php endif; ?>
        
        <!-- Badges -->
        <div class="absolute top-4 right-4 flex flex-col gap-2 items-end">
            <?php if($product['sale_price']): ?>
                <span class="bg-rose-500 text-white text-[10px] font-bold px-3 py-1 rounded-lg shadow-lg uppercase tracking-widest">Sale</span>
            <?php endif; ?>
            
            <?php if($discount > 0): ?>
                <span class="bg-amber-500 text-black text-[10px] font-bold px-3 py-1 rounded-lg shadow-lg uppercase tracking-widest">-<?php echo $discount; ?>%</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Card Body -->
    <div class="p-6 flex-grow flex flex-col">
        <div class="flex items-center gap-2 mb-3">
            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest"><?php echo htmlspecialchars($product['cat_name'] ?? 'General'); ?></span>
        </div>

        <h3 class="text-white font-bold text-lg mb-3 line-clamp-2 group-hover:text-blue-400 transition-colors leading-tight">
            <?php echo htmlspecialchars($product['name']); ?>
        </h3>
        
        <p class="text-slate-500 text-xs line-clamp-2 mb-6 leading-relaxed">
            <?php echo htmlspecialchars($product['user_instruction'] ?: "Fast and secure delivery to your account."); ?>
        </p>
        
        <!-- Footer -->
        <div class="mt-auto pt-4 border-t border-white/5 flex items-center justify-between">
            <div class="flex flex-col">
                <span class="text-2xl font-bold <?php echo ($product['sale_price']||$discount>0)?'text-amber-400':'text-white'; ?>">
                    <?php echo format_price($final_price); ?>
                </span>
                <?php if($product['sale_price'] || $discount > 0): ?>
                    <span class="text-[10px] text-slate-500 line-through font-medium">
                        <?php echo format_price($product['price']); ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <div class="w-10 h-10 rounded-full bg-slate-800 group-hover:bg-blue-600 flex items-center justify-center text-white transition-all transform group-hover:rotate-[-45deg]">
                <i class="fas fa-arrow-right text-xs"></i>
            </div>
        </div>
    </div>
</a>
