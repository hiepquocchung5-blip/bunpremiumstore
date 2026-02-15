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

// Determine Icon (Fallback if not set)
$icon = $product['icon_class'] ?? 'fa-cube';
?>

<div class="glass-card p-0 rounded-xl overflow-hidden group hover:border-blue-500/50 transition duration-300 relative flex flex-col h-full bg-gray-900/40 border border-gray-700/50">
    
    <!-- Badges Container -->
    <div class="absolute top-3 right-3 flex flex-col gap-1 items-end z-10">
        <?php if($product['sale_price']): ?>
            <span class="bg-red-500 text-white text-[10px] font-bold px-2 py-1 rounded shadow-lg animate-pulse">SALE</span>
        <?php endif; ?>
        
        <?php if($discount > 0): ?>
            <span class="bg-yellow-500 text-black text-[10px] font-bold px-2 py-1 rounded shadow-lg flex items-center gap-1">
                <i class="fas fa-crown text-[8px]"></i> -<?php echo $discount; ?>%
            </span>
        <?php endif; ?>
    </div>

    <!-- Card Body -->
    <div class="p-5 flex-grow flex flex-col">
        
        <!-- Icon / Category -->
        <div class="flex items-start justify-between mb-4">
            <div class="w-12 h-12 bg-gray-800 rounded-lg flex items-center justify-center text-2xl text-blue-500 group-hover:scale-110 transition border border-gray-700 shadow-inner group-hover:text-blue-400 group-hover:border-blue-500/30">
                <i class="fas <?php echo htmlspecialchars($icon); ?>"></i>
            </div>
            <span class="text-[10px] font-bold text-gray-500 bg-gray-800 px-2 py-1 rounded border border-gray-700 uppercase tracking-wider">
                <?php echo htmlspecialchars($product['cat_name']); ?>
            </span>
        </div>

        <!-- Title -->
        <h3 class="text-lg font-bold text-white mb-2 line-clamp-2 group-hover:text-blue-400 transition leading-snug">
            <a href="index.php?module=shop&page=product&id=<?php echo $product['id']; ?>" class="focus:outline-none">
                <?php echo htmlspecialchars($product['name']); ?>
            </a>
        </h3>
        
        <!-- Instruction Snippet -->
        <p class="text-xs text-gray-400 line-clamp-2 h-8 mb-4 opacity-80">
            <?php echo htmlspecialchars($product['user_instruction'] ?: "Instant delivery available via auto-system."); ?>
        </p>
        
        <!-- Pricing Footer -->
        <div class="mt-auto pt-4 border-t border-gray-700/50 flex items-center justify-between">
            <div>
                <p class="text-[10px] text-gray-500 uppercase font-bold mb-0.5">Price</p>
                <div class="flex items-baseline gap-2">
                    <span class="text-lg font-bold <?php echo ($product['sale_price']||$discount>0)?'text-yellow-400':'text-white'; ?>">
                        <?php echo format_price($final_price); ?>
                    </span>
                    
                    <?php if($product['sale_price'] || $discount > 0): ?>
                        <span class="text-xs text-gray-600 line-through decoration-gray-500">
                            <?php echo format_price($product['price']); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <a href="index.php?module=shop&page=product&id=<?php echo $product['id']; ?>" 
               class="w-10 h-10 bg-blue-600 hover:bg-blue-500 text-white rounded-full flex items-center justify-center shadow-lg transform group-hover:scale-110 transition focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 focus:ring-offset-gray-900"
               title="View Details">
                <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
</div>