<?php
// modules/home/product_card.php

/**
 * Expects: 
 * $product (array from DB)
 * $discount (int - User's agent discount percentage)
 */

// Calculate Final Price
// Priority: Sale Price -> Regular Price
$base_price = $product['sale_price'] ?: $product['price'];

// Apply Agent Discount to the base
$final_price = $base_price * ((100 - $discount) / 100);

// Determine Image/Icon to display
$has_product_image = !empty($product['image_path']);
$has_cat_image = !empty($product['cat_image']);
$fallback_icon = $product['icon_class'] ?? 'fa-cube'; 
?>

<div class="glass-card p-0 rounded-2xl overflow-hidden group hover:border-[#00f0ff]/40 transition-all duration-300 relative flex flex-col h-full bg-slate-900/60 border border-slate-700/50 shadow-[0_10px_30px_rgba(0,0,0,0.3)] hover:shadow-[0_10px_40px_rgba(0,240,255,0.15)]">
    
    <!-- Badges Container -->
    <div class="absolute top-4 right-4 flex flex-col gap-1.5 items-end z-20">
        <?php if($product['sale_price']): ?>
            <span class="bg-red-500/90 backdrop-blur border border-red-400 text-white text-[9px] font-black px-2.5 py-1 rounded-md shadow-[0_0_15px_rgba(239,68,68,0.5)] animate-pulse uppercase tracking-widest">
                Flash Sale
            </span>
        <?php endif; ?>
        
        <?php if($discount > 0): ?>
            <span class="bg-gradient-to-r from-yellow-500 to-yellow-600 text-slate-900 text-[9px] font-black px-2.5 py-1 rounded-md shadow-lg flex items-center gap-1 uppercase tracking-widest">
                <i class="fas fa-crown text-[8px]"></i> -<?php echo $discount; ?>%
            </span>
        <?php endif; ?>
    </div>

    <!-- Card Body -->
    <div class="p-6 flex-grow flex flex-col relative z-10">
        
        <!-- Icon / Category Header -->
        <div class="flex items-start justify-between mb-5">
            <div class="w-14 h-14 bg-slate-800 rounded-xl flex items-center justify-center text-2xl text-[#00f0ff] group-hover:scale-110 transition-transform duration-500 border border-slate-600 shadow-inner overflow-hidden relative shrink-0">
                <?php if($has_product_image): ?>
                    <img src="<?php echo BASE_URL . $product['image_path']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-full h-full object-cover">
                <?php elseif($has_cat_image): ?>
                    <img src="<?php echo BASE_URL . $product['cat_image']; ?>" alt="Category" class="w-full h-full object-cover">
                <?php else: ?>
                    <i class="fas <?php echo htmlspecialchars($fallback_icon); ?>"></i>
                <?php endif; ?>
            </div>
            
            <span class="text-[9px] font-black text-slate-400 bg-slate-950/50 px-2.5 py-1 rounded-md border border-slate-700/50 uppercase tracking-widest mt-1">
                <?php echo htmlspecialchars($product['cat_name'] ?? 'Unknown'); ?>
            </span>
        </div>

        <!-- Title -->
        <h3 class="text-lg md:text-xl font-black text-white mb-2 line-clamp-2 group-hover:text-[#00f0ff] transition-colors duration-300 tracking-tight leading-snug">
            <!-- Full card click target -->
            <a href="index.php?module=shop&page=product&id=<?php echo $product['id']; ?>" class="focus:outline-none before:absolute before:inset-0">
                <?php echo htmlspecialchars($product['name']); ?>
            </a>
        </h3>
        
        <!-- Instruction Snippet -->
        <p class="text-xs text-slate-400 line-clamp-2 h-8 mb-6 opacity-80 leading-relaxed font-medium">
            <?php echo htmlspecialchars($product['user_instruction'] ?: "Secure digital transfer available via automated matrix."); ?>
        </p>
        
        <!-- Pricing Footer -->
        <div class="mt-auto pt-5 border-t border-slate-700/50 flex items-end justify-between relative z-20 pointer-events-none">
            <div>
                <p class="text-[9px] text-[#00f0ff] uppercase font-black tracking-widest mb-1 opacity-80">Transfer Value</p>
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-black tracking-tighter <?php echo ($product['sale_price']||$discount>0)?'text-yellow-400 drop-shadow-[0_0_8px_rgba(234,179,8,0.4)]':'text-white'; ?>">
                        <?php echo format_price($final_price); ?>
                    </span>
                    
                    <?php if($product['sale_price'] || $discount > 0): ?>
                        <span class="text-xs text-slate-500 line-through decoration-red-500/50 font-mono font-medium">
                            <?php echo format_price($product['price']); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="w-10 h-10 bg-slate-800 text-slate-400 group-hover:bg-gradient-to-r group-hover:from-blue-600 group-hover:to-[#00f0ff] group-hover:text-slate-900 rounded-xl flex items-center justify-center shadow-lg transform group-hover:-translate-y-1 transition-all duration-300 border border-slate-600 group-hover:border-transparent pointer-events-auto cursor-pointer">
                <i class="fas fa-arrow-right text-sm"></i>
            </div>
        </div>
    </div>
</div>