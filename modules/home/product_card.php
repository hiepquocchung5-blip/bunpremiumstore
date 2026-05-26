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
<a href="<?php echo $product_url; ?>" class="glass-card block p-0 rounded-2xl overflow-hidden group hover:border-[#00f0ff]/50 transition-all duration-500 relative flex flex-col h-full bg-slate-900/80 border border-slate-700/50 shadow-[0_10px_30px_rgba(0,0,0,0.3)] hover:shadow-[0_15px_40px_rgba(0,240,255,0.2)] hover:-translate-y-2">
    
    <!-- Hover Circuit Grid Background -->
    <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iMiIgY3k9IjIiIHI9IjEiIGZpbGw9InJnYmEoMCwgMjQwLCAyNTUsIDAuMSkiLz48L3N2Zz4=')] opacity-0 group-hover:opacity-100 transition-opacity duration-500 z-0 pointer-events-none"></div>

    <!-- Ambient Corner Glow -->
    <div class="absolute -top-12 -right-12 w-32 h-32 bg-[#00f0ff]/10 rounded-full blur-3xl group-hover:bg-[#00f0ff]/20 transition-colors duration-500 z-0 pointer-events-none"></div>

    <!-- Badges Container -->
    <div class="absolute top-4 right-4 flex flex-col gap-2 items-end z-20">
        <?php if($product['sale_price']): ?>
            <span class="bg-red-500/90 backdrop-blur-sm border border-red-400 text-white text-[10px] font-bold px-2.5 py-1 rounded-md shadow-[0_0_15px_rgba(239,68,68,0.5)] animate-pulse uppercase tracking-wide">
                Flash Sale
            </span>
        <?php endif; ?>
        
        <?php if($discount > 0): ?>
            <span class="bg-gradient-to-r from-yellow-500 to-yellow-600 text-slate-900 text-[10px] font-bold px-2.5 py-1 rounded-md shadow-lg flex items-center gap-1 uppercase tracking-wide">
                <i class="fas fa-crown text-[9px]"></i> -<?php echo $discount; ?>%
            </span>
        <?php endif; ?>
    </div>

    <!-- Card Body -->
    <div class="p-4 sm:p-5 md:p-6 flex-grow flex flex-col relative z-10">
        
        <!-- Icon / Category Header -->
        <div class="flex items-start justify-between mb-4">
            <div class="w-12 h-12 sm:w-14 sm:h-14 bg-slate-800 rounded-xl flex items-center justify-center text-xl sm:text-2xl text-[#00f0ff] group-hover:scale-110 group-hover:shadow-[0_0_20px_rgba(0,240,255,0.3)] transition-all duration-500 border border-slate-600 shadow-inner overflow-hidden relative shrink-0">
                <?php if($has_product_image): ?>
                    <img src="<?php echo BASE_URL . $product['image_path']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-full h-full object-cover" loading="lazy">
                <?php elseif($has_cat_image): ?>
                    <img src="<?php echo BASE_URL . $product['cat_image']; ?>" alt="Category" class="w-full h-full object-cover" loading="lazy">
                <?php else: ?>
                    <i class="fas <?php echo htmlspecialchars($fallback_icon); ?>"></i>
                <?php endif; ?>
            </div>
            
            <span class="text-[10px] font-bold text-slate-300 bg-slate-800/80 px-3 py-1 rounded-full border border-slate-600 shadow-sm mt-1">
                <?php echo htmlspecialchars($product['cat_name'] ?? 'Unknown'); ?>
            </span>
        </div>

        <!-- Title -->
        <h3 class="text-base sm:text-lg md:text-xl font-bold text-white mb-2 line-clamp-2 group-hover:text-[#00f0ff] transition-colors duration-300 leading-tight">
            <?php echo htmlspecialchars($product['name']); ?>
        </h3>
        
        <!-- Instruction Snippet (Simplified for Users) -->
        <p class="text-xs text-slate-400 line-clamp-2 h-8 mb-6 leading-relaxed group-hover:text-slate-300 transition-colors">
            <?php echo htmlspecialchars($product['user_instruction'] ?: "Instant secure delivery to your account upon purchase."); ?>
        </p>
        
        <!-- Pricing & CTA Footer -->
        <div class="mt-auto pt-4 border-t border-slate-700/50 flex items-end justify-between relative z-20">
            <div>
                <p class="text-[10px] text-slate-400 uppercase font-semibold tracking-wide mb-1">Price</p>
                <div class="flex items-baseline gap-2">
                    <span class="text-xl sm:text-2xl font-black tracking-tight <?php echo ($product['sale_price']||$discount>0)?'text-yellow-400 drop-shadow-[0_0_8px_rgba(234,179,8,0.4)]':'text-white'; ?>">
                        <?php echo format_price($final_price); ?>
                    </span>
                    
                    <?php if($product['sale_price'] || $discount > 0): ?>
                        <span class="text-xs text-slate-500 line-through decoration-red-500/50 font-medium">
                            <?php echo format_price($product['price']); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Animated Launch Button (User-Friendly CTA) -->
            <span class="flex items-center gap-2 px-3 sm:px-4 py-2.5 bg-slate-800 text-slate-300 group-hover:bg-gradient-to-r group-hover:from-blue-600 group-hover:to-[#00f0ff] group-hover:text-slate-900 rounded-xl shadow-md transition-all duration-500 border border-slate-600 group-hover:border-transparent group-hover:shadow-[0_0_15px_rgba(0,240,255,0.5)] font-bold text-[10px] sm:text-xs shrink-0">
                <span class="hidden sm:inline">Get It Now</span>
                <span class="sm:hidden">Buy</span>
                <i class="fas fa-arrow-right transform group-hover:rotate-[-45deg] group-hover:scale-110 transition-transform duration-500"></i>
            </span>
        </div>
    </div>
</a>
