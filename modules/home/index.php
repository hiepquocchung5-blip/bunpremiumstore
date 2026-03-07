<?php
// modules/home/index.php
// PRODUCTION READY v1.2 - Enhanced Slider & Category Images UI

// 1. Fetch Banners (Active Slides)
$stmt = $pdo->query("SELECT * FROM banners ORDER BY display_order ASC, id DESC LIMIT 5");
$banners = $stmt->fetchAll();

// 2. Fetch Categories (Now fetching image_url)
$stmt = $pdo->query("SELECT * FROM categories ORDER BY id ASC");
$categories = $stmt->fetchAll();

// 3. Fetch "Hot Deals" (Sale Items)
$stmt = $pdo->query("
    SELECT p.*, c.name as cat_name, c.image_url as cat_image
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    WHERE p.sale_price IS NOT NULL AND p.sale_price < p.price
    ORDER BY RAND() LIMIT 4
");
$flash_sales = $stmt->fetchAll();

// 4. Fetch "Best Sellers" (Based on active orders)
$stmt = $pdo->query("
    SELECT p.*, c.name as cat_name, c.image_url as cat_image, COUNT(o.id) as sales_count
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    LEFT JOIN orders o ON p.id = o.product_id AND o.status = 'active'
    GROUP BY p.id
    ORDER BY sales_count DESC LIMIT 5
");
$best_sellers = $stmt->fetchAll();

// 5. Fetch "Recent Arrivals" (Main Grid)
$stmt = $pdo->query("
    SELECT p.*, c.name as cat_name, c.image_url as cat_image
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    ORDER BY p.id DESC LIMIT 12
");
$recent_products = $stmt->fetchAll();

// 6. Fetch "Community Trust" (Recent 5-star reviews)
$stmt = $pdo->query("
    SELECT r.*, u.username, p.name as product_name, p.id as pid
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    JOIN products p ON r.product_id = p.id
    WHERE r.rating >= 4
    ORDER BY r.created_at DESC LIMIT 3
");
$recent_reviews = $stmt->fetchAll();

// 7. Get Current User Discount
$discount = is_logged_in() ? get_user_discount($_SESSION['user_id']) : 0;
?>

<style>
    /* Custom Scroll & Animations */
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    
    .glass-card {
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(16px);
        border: 1px solid rgba(255, 255, 255, 0.05);
        box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .glass-card:hover {
        background: rgba(15, 23, 42, 0.8);
        border-color: rgba(0, 240, 255, 0.3);
        transform: translateY(-4px);
        box-shadow: 0 10px 25px -5px rgba(0, 240, 255, 0.15);
    }
    
    .animate-marquee { animation: marquee 25s linear infinite; }
    @keyframes marquee { 0% { transform: translateX(100%); } 100% { transform: translateX(-100%); } }

    /* Slider Scroll Snapping */
    .snap-x-mandatory {
        scroll-snap-type: x mandatory;
        -webkit-overflow-scrolling: touch;
    }
    .snap-center { scroll-snap-align: center; }
</style>

<!-- SECTION 0: News Ticker -->
<div class="w-full bg-slate-900 border-b border-slate-800 overflow-hidden py-1.5 mb-6">
    <div class="whitespace-nowrap animate-marquee text-xs text-[#00f0ff] font-mono tracking-widest uppercase font-bold">
        🚀 Welcome to DigitalMarketplaceMM • Instant Delivery 24/7 • Official Game Keys & Premium Accounts • Join our Telegram for Giveaways! • Verified KBZPay/Wave Agents
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    
    <!-- SECTION 1: Responsive Banner Slider -->
    <?php if(!empty($banners)): ?>
    <div class="relative w-full h-[180px] sm:h-[280px] md:h-[350px] lg:h-[400px] mb-12 rounded-3xl overflow-hidden group shadow-[0_20px_50px_rgba(0,0,0,0.5)] bg-slate-900 border border-[#00f0ff]/20" id="sliderContainer">
        
        <div class="flex overflow-x-auto snap-x-mandatory h-full no-scrollbar scroll-smooth" id="bannerSlider">
            <?php foreach($banners as $index => $b): ?>
                <div class="w-full flex-shrink-0 snap-center relative h-full banner-slide" data-index="<?php echo $index; ?>">
                    <a href="<?php echo $b['target_url'] ?: '#'; ?>" target="<?php echo $b['target_url'] ? '_blank' : '_self'; ?>" class="block w-full h-full cursor-pointer">
                        <img src="<?php echo BASE_URL . $b['image_path']; ?>" class="w-full h-full object-cover transition duration-1000 group-hover:scale-105" loading="lazy" alt="<?php echo htmlspecialchars($b['title']); ?>">
                        
                        <!-- Gradient Overlay -->
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-900/40 to-transparent opacity-90"></div>
                        
                        <!-- Banner Text -->
                        <div class="absolute bottom-0 left-0 p-6 sm:p-10 w-full z-10">
                            <h3 class="text-white text-2xl sm:text-4xl md:text-5xl font-black drop-shadow-[0_0_10px_rgba(0,240,255,0.5)] mb-3 transform transition translate-y-0 group-hover:-translate-y-2 tracking-tight"><?php echo htmlspecialchars($b['title']); ?></h3>
                            <div class="h-1 sm:h-1.5 w-16 sm:w-24 bg-gradient-to-r from-blue-600 to-[#00f0ff] rounded-full shadow-[0_0_10px_rgba(0,240,255,0.8)]"></div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Navigation Dots -->
        <div class="absolute bottom-6 right-6 flex gap-2.5 z-20" id="sliderDots">
            <?php foreach($banners as $i => $b): ?>
                <button class="w-2.5 h-2.5 rounded-full transition-all duration-300 slider-dot <?php echo $i === 0 ? 'bg-[#00f0ff] shadow-[0_0_10px_rgba(0,240,255,0.8)] w-6' : 'bg-white/30 hover:bg-white/60'; ?>" data-target="<?php echo $i; ?>"></button>
            <?php endforeach; ?>
        </div>
        
        <!-- Arrow Controls -->
        <button id="prevSlide" class="absolute left-4 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-slate-900/50 backdrop-blur border border-white/10 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition duration-300 hover:bg-[#00f0ff] hover:text-slate-900"><i class="fas fa-chevron-left"></i></button>
        <button id="nextSlide" class="absolute right-4 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-slate-900/50 backdrop-blur border border-white/10 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition duration-300 hover:bg-[#00f0ff] hover:text-slate-900"><i class="fas fa-chevron-right"></i></button>
    </div>
    <?php else: ?>
        <div class="relative w-full h-[200px] md:h-[300px] mb-12 rounded-3xl bg-slate-900 flex items-center justify-center border border-[#00f0ff]/20 shadow-[0_20px_50px_rgba(0,0,0,0.5)]">
            <div class="text-center">
                <h2 class="text-4xl md:text-5xl font-black text-white mb-2 tracking-tight">Digital<span class="text-[#00f0ff] drop-shadow-[0_0_10px_rgba(0,240,255,0.8)]">MM</span></h2>
                <p class="text-slate-400 uppercase tracking-widest font-bold text-xs md:text-sm">Premium Digital Network</p>
            </div>
        </div>
    <?php endif; ?>


    <!-- SECTION 2: Categories (Image Centric) -->
    <div class="mb-16">
        <h2 class="text-2xl md:text-3xl font-black text-white mb-6 tracking-tight flex items-center gap-3">
            <i class="fas fa-network-wired text-[#00f0ff]"></i> Sector Directory
        </h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 sm:gap-6">
            <?php foreach($categories as $cat): ?>
                <a href="index.php?module=shop&page=category&id=<?php echo $cat['id']; ?>" class="glass-card rounded-2xl overflow-hidden group relative flex flex-col justify-end aspect-square sm:aspect-auto sm:h-40 border border-slate-700/50">
                    
                    <!-- Dynamic Background -->
                    <?php if(!empty($cat['image_url'])): ?>
                        <img src="<?php echo BASE_URL . $cat['image_url']; ?>" alt="<?php echo htmlspecialchars($cat['name']); ?>" class="absolute inset-0 w-full h-full object-cover opacity-50 group-hover:opacity-80 transition duration-500 group-hover:scale-110">
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-900/60 to-transparent"></div>
                    <?php else: ?>
                        <div class="absolute inset-0 bg-slate-800 flex items-center justify-center group-hover:scale-110 transition duration-500">
                            <!-- Fallback Icon if no image URL -->
                            <i class="fas fa-folder text-6xl text-slate-700 group-hover:text-[#00f0ff]/20 transition-colors"></i>
                        </div>
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950 to-transparent"></div>
                    <?php endif; ?>

                    <!-- Content -->
                    <div class="relative z-10 p-4 text-center w-full transform transition group-hover:-translate-y-1">
                        <?php if(empty($cat['image_url'])): ?>
                             <i class="fas fa-folder text-[#00f0ff] text-xl mb-1.5 drop-shadow-[0_0_5px_rgba(0,240,255,0.8)]"></i>
                        <?php endif; ?>
                        <h3 class="text-sm font-black text-white uppercase tracking-wider drop-shadow-md"><?php echo htmlspecialchars($cat['name']); ?></h3>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- SECTION 3: Hot Deals (Flash Sales) -->
    <?php if(!empty($flash_sales)): ?>
    <div class="mb-16">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-3">
            <div class="flex items-center gap-3">
                <span class="relative flex h-4 w-4">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-4 w-4 bg-red-500 shadow-[0_0_10px_rgba(239,68,68,0.8)]"></span>
                </span>
                <h2 class="text-2xl md:text-3xl font-black text-white tracking-tight">Flash Sales</h2>
            </div>
            <div class="text-xs font-mono text-red-400 font-bold bg-red-900/20 px-3 py-1.5 rounded-lg border border-red-900/50 uppercase tracking-widest w-fit">Limited Time Offer</div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php foreach($flash_sales as $product): 
                include __DIR__ . '/product_card.php'; 
            endforeach; ?>
        </div>
    </div>
    <?php endif; ?>


    <!-- SECTION 4: Feature Strip & Telegram Ad -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-16">
        
        <!-- Telegram Ad Card -->
        <a href="https://t.me/bunpremiumstore" target="_blank" class="md:col-span-1 bg-gradient-to-br from-blue-600 to-[#00f0ff] rounded-2xl p-5 flex items-center justify-between shadow-[0_10px_30px_rgba(0,240,255,0.2)] hover:shadow-[0_15px_40px_rgba(0,240,255,0.4)] transition duration-300 group relative overflow-hidden transform hover:-translate-y-1">
            <div class="relative z-10">
                <h4 class="text-slate-900 font-black text-sm uppercase tracking-widest">Join Network</h4>
                <p class="text-slate-800 text-xs mt-1 font-bold">Live Updates & Drops</p>
            </div>
            <div class="w-12 h-12 bg-white/20 backdrop-blur rounded-full flex items-center justify-center text-white relative z-10 group-hover:scale-110 transition duration-300 border border-white/30">
                <i class="fab fa-telegram-plane text-2xl"></i>
            </div>
            <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-white/20 rounded-full blur-2xl group-hover:bg-white/30 transition"></div>
        </a>

        <!-- Feature 1 -->
        <div class="glass border border-slate-700/50 p-4 rounded-2xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-green-500/10 flex items-center justify-center text-green-400 text-xl border border-green-500/20 shadow-inner"><i class="fas fa-bolt"></i></div>
            <div><div class="text-sm font-bold text-white tracking-wide">Instant Delivery</div><div class="text-xs text-slate-400">Automated 24/7</div></div>
        </div>

        <!-- Feature 2 -->
        <div class="glass border border-slate-700/50 p-4 rounded-2xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-purple-500/10 flex items-center justify-center text-purple-400 text-xl border border-purple-500/20 shadow-inner"><i class="fas fa-shield-alt"></i></div>
            <div><div class="text-sm font-bold text-white tracking-wide">Official Warranty</div><div class="text-xs text-slate-400">Secure Protocol</div></div>
        </div>

        <!-- Feature 3 -->
        <div class="glass border border-slate-700/50 p-4 rounded-2xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-yellow-500/10 flex items-center justify-center text-yellow-400 text-xl border border-yellow-500/20 shadow-inner"><i class="fas fa-wallet"></i></div>
            <div><div class="text-sm font-bold text-white tracking-wide">Local Payment</div><div class="text-xs text-slate-400">Kpay / Wave Pay</div></div>
        </div>
    </div>


    <!-- SECTION 5: Main Hub (Sidebar + Grid) -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8 mb-16">
        
        <!-- LEFT SIDEBAR -->
        <div class="lg:col-span-1 space-y-8">
            
            <!-- Trending -->
            <div class="glass border border-slate-700/50 rounded-3xl p-6 shadow-xl">
                <h3 class="text-lg font-black text-white flex items-center gap-2 mb-6 uppercase tracking-wider">
                    <i class="fas fa-fire text-orange-500"></i> Trending
                </h3>
                <div class="space-y-5">
                    <?php foreach($best_sellers as $product): ?>
                        <?php 
                            $b_base = $product['sale_price'] ?: $product['price'];
                            $b_final = $b_base * ((100 - $discount) / 100);
                        ?>
                        <a href="index.php?module=shop&page=product&id=<?php echo $product['id']; ?>" class="flex items-center gap-4 group">
                            
                            <div class="w-14 h-14 rounded-xl bg-slate-900 flex items-center justify-center text-[#00f0ff] border border-slate-700 shrink-0 group-hover:border-[#00f0ff]/50 transition overflow-hidden shadow-inner">
                                <?php if(!empty($product['image_path'])): ?>
                                    <img src="<?php echo BASE_URL . $product['image_path']; ?>" class="w-full h-full object-cover">
                                <?php elseif(!empty($product['cat_image'])): ?>
                                    <img src="<?php echo BASE_URL . $product['cat_image']; ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <i class="fas fa-cube text-xl"></i>
                                <?php endif; ?>
                            </div>
                            
                            <div class="min-w-0">
                                <h4 class="text-sm font-bold text-slate-200 truncate group-hover:text-[#00f0ff] transition"><?php echo htmlspecialchars($product['name']); ?></h4>
                                <div class="flex items-center gap-2 text-[10px] mt-1">
                                    <span class="text-green-400 font-mono font-bold bg-green-900/20 px-2 py-0.5 rounded border border-green-500/20"><?php echo format_price($b_final); ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Reviews -->
            <?php if(!empty($recent_reviews)): ?>
            <div class="glass border border-slate-700/50 rounded-3xl p-6 shadow-xl">
                <h3 class="text-lg font-black text-white mb-6 flex items-center gap-2 uppercase tracking-wider">
                    <i class="fas fa-comments text-[#00f0ff]"></i> Network Comms
                </h3>
                <div class="space-y-4">
                    <?php foreach($recent_reviews as $r): ?>
                        <a href="index.php?module=shop&page=product&id=<?php echo $r['pid']; ?>" class="block p-4 rounded-2xl bg-slate-900/50 border border-slate-700/50 hover:border-[#00f0ff]/30 transition group">
                            <div class="flex justify-between items-start mb-2">
                                <span class="text-xs font-bold text-slate-300 group-hover:text-white transition">@<?php echo htmlspecialchars($r['username']); ?></span>
                                <div class="flex text-[8px] text-yellow-500">
                                    <?php for($i=0; $i<$r['rating']; $i++) echo '<i class="fas fa-star"></i>'; ?>
                                </div>
                            </div>
                            <p class="text-[11px] text-slate-400 italic line-clamp-3 leading-relaxed">"<?php echo htmlspecialchars($r['comment']); ?>"</p>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- RIGHT MAIN GRID: New Arrivals -->
        <div class="lg:col-span-3">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end mb-6 gap-2">
                <div>
                    <h2 class="text-2xl md:text-3xl font-black text-white mb-1 tracking-tight">New Deployments</h2>
                    <p class="text-slate-400 text-sm font-medium">Fresh stock injected into the matrix</p>
                </div>
                <a href="index.php?module=shop&page=search" class="text-[#00f0ff] hover:text-white text-sm font-bold flex items-center gap-2 transition uppercase tracking-wider bg-[#00f0ff]/10 px-4 py-2 rounded-lg border border-[#00f0ff]/20 hover:bg-[#00f0ff]/20">
                    View Matrix <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">
                <?php foreach($recent_products as $product): 
                    include __DIR__ . '/product_card.php'; 
                endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Bottom CTA (Reseller) -->
    <?php if($discount == 0): ?>
    <div class="relative rounded-3xl overflow-hidden border border-yellow-500/30 shadow-[0_20px_50px_rgba(0,0,0,0.5)]">
        <div class="absolute inset-0 bg-gradient-to-r from-yellow-900/90 via-slate-900 to-slate-900 z-10"></div>
        <div class="absolute -left-20 -bottom-20 w-64 h-64 bg-yellow-500/20 rounded-full blur-3xl pointer-events-none z-10"></div>
        
        <div class="relative z-20 p-8 md:p-12 flex flex-col md:flex-row items-center justify-between gap-6 text-center md:text-left">
            <div>
                <h2 class="text-2xl md:text-4xl font-black text-white mb-2 tracking-tight">Establish Your Node</h2>
                <p class="text-yellow-200/80 max-w-lg text-sm md:text-base">Join our Reseller Program and unlock up to <span class="text-yellow-400 font-black text-lg">35% OFF</span> all global products. Instant activation sequence.</p>
            </div>
            <a href="index.php?module=user&page=agent" class="bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-400 hover:to-yellow-500 text-slate-900 font-black px-8 py-4 rounded-xl shadow-[0_0_20px_rgba(234,179,8,0.4)] transform hover:-translate-y-1 transition uppercase tracking-widest whitespace-nowrap flex items-center gap-2">
                <i class="fas fa-crown"></i> Become an Agent
            </a>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- SLIDER JS LOGIC -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const slider = document.getElementById('bannerSlider');
    const dots = document.querySelectorAll('.slider-dot');
    const prevBtn = document.getElementById('prevSlide');
    const nextBtn = document.getElementById('nextSlide');
    
    if (!slider) return;

    let slideInterval;
    const intervalTime = 5000; // 5 seconds per slide

    const updateDots = (index) => {
        dots.forEach((dot, i) => {
            if (i === index) {
                dot.className = 'w-6 h-2.5 rounded-full transition-all duration-300 slider-dot bg-[#00f0ff] shadow-[0_0_10px_rgba(0,240,255,0.8)]';
            } else {
                dot.className = 'w-2.5 h-2.5 rounded-full transition-all duration-300 slider-dot bg-white/30 hover:bg-white/60';
            }
        });
    };

    const goToSlide = (index) => {
        const slideWidth = slider.clientWidth;
        slider.scrollTo({
            left: index * slideWidth,
            behavior: 'smooth'
        });
        updateDots(index);
    };

    const nextSlide = () => {
        const slideWidth = slider.clientWidth;
        const maxScroll = slider.scrollWidth - slideWidth;
        let nextPos = slider.scrollLeft + slideWidth;
        
        // If at the end, go back to start
        if (nextPos >= maxScroll + 10) { 
            nextPos = 0;
        }
        
        slider.scrollTo({ left: nextPos, behavior: 'smooth' });
        const newIndex = Math.round(nextPos / slideWidth);
        updateDots(newIndex);
    };

    const prevSlideFn = () => {
        const slideWidth = slider.clientWidth;
        let prevPos = slider.scrollLeft - slideWidth;
        
        // If at start, go to end
        if (prevPos < 0) {
            prevPos = slider.scrollWidth - slideWidth;
        }
        
        slider.scrollTo({ left: prevPos, behavior: 'smooth' });
        const newIndex = Math.round(prevPos / slideWidth);
        updateDots(newIndex);
    };

    // Auto Scroll
    const startAutoScroll = () => {
        slideInterval = setInterval(nextSlide, intervalTime);
    };
    
    const stopAutoScroll = () => {
        clearInterval(slideInterval);
    };

    // Event Listeners
    if(nextBtn) nextBtn.addEventListener('click', () => { stopAutoScroll(); nextSlide(); startAutoScroll(); });
    if(prevBtn) prevBtn.addEventListener('click', () => { stopAutoScroll(); prevSlideFn(); startAutoScroll(); });

    dots.forEach((dot, index) => {
        dot.addEventListener('click', () => {
            stopAutoScroll();
            goToSlide(index);
            startAutoScroll();
        });
    });

    // Handle manual scroll update
    let isScrolling;
    slider.addEventListener('scroll', () => {
        window.clearTimeout(isScrolling);
        isScrolling = setTimeout(() => {
            const slideWidth = slider.clientWidth;
            const currentIndex = Math.round(slider.scrollLeft / slideWidth);
            updateDots(currentIndex);
        }, 100);
    });

    // Pause on hover
    slider.addEventListener('mouseenter', stopAutoScroll);
    slider.addEventListener('mouseleave', startAutoScroll);

    // Initialize
    startAutoScroll();
});
</script>