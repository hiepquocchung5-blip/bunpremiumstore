<?php
// modules/shop/category.php
// PRODUCTION v3.1 - Dynamic Global Hub with Mobile Responsive Sidebar

$cat_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

$trending_products = [];
$recommended_products = [];

// 1. Fetch Current Category Details or Set to Global View
if ($cat_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$cat_id]);
    $current_category = $stmt->fetch();

    if (!$current_category) {
        echo "
        <div class='flex flex-col items-center justify-center min-h-[60vh] text-center px-4 relative'>
            <div class='absolute inset-0 bg-red-500/10 blur-3xl rounded-full w-64 h-64 mx-auto'></div>
            <i class='fas fa-database text-7xl mb-4 text-red-500 relative z-10 animate-pulse'></i>
            <h2 class='text-3xl font-black text-white mb-2 relative z-10'>Sector Offline</h2>
            <p class='text-slate-400 max-w-md mb-6 relative z-10'>The requested category matrix could not be located in the database.</p>
            <a href='index.php' class='bg-gradient-to-r from-blue-600 to-[#00f0ff] hover:from-blue-500 hover:to-[#00f0ff] text-slate-900 font-black px-8 py-3 rounded-xl shadow-[0_0_20px_rgba(0,240,255,0.3)] transition transform hover:-translate-y-1 relative z-10'>
                Return to Hub
            </a>
        </div>";
        return;
    }
} else {
    // Virtual Category for "All Products"
    $current_category = [
        'id' => 0,
        'name' => 'Global Store Network',
        'type' => 'omni',
        'icon_class' => 'fa-globe',
        'description' => 'Browse all active digital assets, trending items, and recommended nodes across every sector in the network.'
    ];
}

// 2. Fetch ALL Categories for Sidebar (with product counts)
$stmt = $pdo->query("
    SELECT c.*, 
    (SELECT COUNT(*) FROM products WHERE category_id = c.id) as count 
    FROM categories c 
    ORDER BY c.name ASC
");
$all_categories = $stmt->fetchAll();

// 3. Determine Sorting SQL
$order_sql = "ORDER BY p.id DESC"; // Default: newest
switch ($sort) {
    case 'price_asc':  $order_sql = "ORDER BY COALESCE(p.sale_price, p.price) ASC"; break;
    case 'price_desc': $order_sql = "ORDER BY COALESCE(p.sale_price, p.price) DESC"; break;
    case 'name_asc':   $order_sql = "ORDER BY p.name ASC"; break;
}

// 4. Fetch Products based on Context (Category or Global)
if ($cat_id > 0) {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as cat_name, c.icon_class 
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        WHERE p.category_id = ? 
        $order_sql
    ");
    $stmt->execute([$cat_id]);
    $products = $stmt->fetchAll();
} else {
    // Fetch ALL products for Global View
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as cat_name, c.icon_class 
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        $order_sql
    ");
    $stmt->execute();
    $products = $stmt->fetchAll();

    // Fetch "Trending" (Most Sold)
    $stmt_trend = $pdo->query("
        SELECT p.*, c.name as cat_name, c.icon_class, COUNT(o.id) as sales_count
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        LEFT JOIN orders o ON p.id = o.product_id AND o.status = 'active'
        GROUP BY p.id
        ORDER BY sales_count DESC LIMIT 3
    ");
    $trending_products = $stmt_trend->fetchAll();

    // Fetch "Recommended" (Flash Sales / Hot Deals)
    $stmt_rec = $pdo->query("
        SELECT p.*, c.name as cat_name, c.icon_class 
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        WHERE p.sale_price IS NOT NULL AND p.sale_price < p.price
        ORDER BY RAND() LIMIT 3
    ");
    $recommended_products = $stmt_rec->fetchAll();
}

// 5. Get User Discount
$discount = is_logged_in() ? get_user_discount($_SESSION['user_id']) : 0;
?>

<style>
    /* Animated Blob Backgrounds */
    @keyframes blob {
        0% { transform: translate(0px, 0px) scale(1); }
        33% { transform: translate(30px, -50px) scale(1.1); }
        66% { transform: translate(-20px, 20px) scale(0.9); }
        100% { transform: translate(0px, 0px) scale(1); }
    }
    .animate-blob { animation: blob 7s infinite; }
    .animation-delay-2000 { animation-delay: 2s; }
    .animation-delay-4000 { animation-delay: 4s; }
    
    /* Hide scrollbar for category menu on mobile */
    .hide-scrollbar::-webkit-scrollbar { display: none; }
    .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>

<!-- Animated Background -->
<div class="fixed inset-0 w-full h-full bg-slate-950 -z-20 pointer-events-none"></div>
<div class="fixed top-0 -left-4 w-96 h-96 bg-blue-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-20 animate-blob -z-10 pointer-events-none"></div>
<div class="fixed top-40 -right-4 w-96 h-96 bg-[#00f0ff] rounded-full mix-blend-multiply filter blur-[128px] opacity-10 animate-blob animation-delay-2000 -z-10 pointer-events-none"></div>

<div class="max-w-7xl mx-auto px-4 relative z-0 pb-12">
    
    <!-- Breadcrumb Navigation -->
    <div class="mb-4 flex items-center justify-between">
        <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-slate-500">
            <a href="index.php" class="hover:text-[#00f0ff] transition flex items-center gap-1.5"><i class="fas fa-home"></i> Hub</a>
            <i class="fas fa-chevron-right text-[8px] opacity-50"></i>
            <a href="index.php?module=shop&page=search" class="hover:text-[#00f0ff] transition hidden sm:inline">Store</a>
            <i class="fas fa-chevron-right text-[8px] opacity-50 hidden sm:inline"></i>
            <span class="text-[#00f0ff] truncate max-w-[150px] sm:max-w-none"><?php echo htmlspecialchars($current_category['name']); ?></span>
        </div>
        
        <!-- Mobile Sidebar Toggle -->
        <button id="mobileSidebarToggle" class="lg:hidden bg-slate-800 border border-slate-700 text-white px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-2 hover:bg-slate-700 transition">
            <i class="fas fa-bars"></i> Directory
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 lg:gap-8">
        
        <!-- ========================================== -->
        <!-- LEFT SIDEBAR: Dynamic Category Navigation  -->
        <!-- ========================================== -->
        <!-- Mobile: Hidden by default, toggled via JS. Desktop: Always visible sticky sidebar -->
        <div id="categorySidebar" class="hidden lg:block lg:col-span-1 space-y-6 z-40 fixed inset-0 lg:static lg:bg-transparent bg-slate-950/90 backdrop-blur-sm lg:backdrop-blur-none overflow-y-auto lg:overflow-visible hide-scrollbar pt-20 lg:pt-0 px-4 lg:px-0">
            
            <div class="bg-slate-900/90 backdrop-blur-xl border border-slate-700/50 lg:rounded-2xl rounded-xl p-5 shadow-2xl lg:sticky lg:top-24 max-w-sm mx-auto lg:max-w-none">
                <div class="flex items-center justify-between mb-4 border-b border-slate-700/50 pb-3">
                    <h3 class="font-bold text-white flex items-center gap-2 text-sm uppercase tracking-widest">
                        <i class="fas fa-network-wired text-[#00f0ff]"></i> Sector Directory
                    </h3>
                    <button id="closeSidebarBtn" class="lg:hidden text-slate-400 hover:text-white"><i class="fas fa-times"></i></button>
                </div>
                
                <ul class="space-y-1.5">
                    <!-- Global/All Link -->
                    <li>
                        <a href="index.php?module=shop&page=category" 
                           class="flex items-center justify-between p-3 rounded-xl transition-all duration-300 group <?php echo $cat_id == 0 ? 'bg-[#00f0ff]/10 border border-[#00f0ff]/30 shadow-[0_0_15px_rgba(0,240,255,0.05)]' : 'hover:bg-slate-800 border border-transparent'; ?>">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center <?php echo $cat_id == 0 ? 'bg-[#00f0ff] text-slate-900 shadow-[0_0_10px_rgba(0,240,255,0.5)]' : 'bg-slate-800 text-slate-400 group-hover:text-[#00f0ff] transition-colors'; ?>">
                                    <i class="fas fa-globe text-sm"></i>
                                </div>
                                <span class="font-bold text-sm <?php echo $cat_id == 0 ? 'text-white' : 'text-slate-300 group-hover:text-white transition-colors'; ?>">All Products</span>
                            </div>
                        </a>
                    </li>

                    <li class="my-2 border-b border-slate-700/50"></li>

                    <!-- Individual Categories -->
                    <?php foreach($all_categories as $cat): ?>
                        <li>
                            <a href="index.php?module=shop&page=category&id=<?php echo $cat['id']; ?>" 
                               class="flex items-center justify-between p-3 rounded-xl transition-all duration-300 group <?php echo $cat['id'] == $cat_id ? 'bg-[#00f0ff]/10 border border-[#00f0ff]/30 shadow-[0_0_15px_rgba(0,240,255,0.05)]' : 'hover:bg-slate-800 border border-transparent'; ?>">
                                
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg flex items-center justify-center <?php echo $cat['id'] == $cat_id ? 'bg-[#00f0ff] text-slate-900 shadow-[0_0_10px_rgba(0,240,255,0.5)]' : 'bg-slate-800 text-slate-400 group-hover:text-[#00f0ff] transition-colors'; ?>">
                                        <i class="fas <?php echo htmlspecialchars($cat['icon_class']); ?> text-sm"></i>
                                    </div>
                                    <span class="font-bold text-sm <?php echo $cat['id'] == $cat_id ? 'text-white' : 'text-slate-300 group-hover:text-white transition-colors'; ?>">
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </span>
                                </div>
                                
                                <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded <?php echo $cat['id'] == $cat_id ? 'bg-[#00f0ff]/20 text-[#00f0ff]' : 'bg-slate-800 text-slate-500'; ?>">
                                    <?php echo $cat['count']; ?>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <!-- Promotional Micro-Banner in Sidebar -->
                <?php if($discount == 0): ?>
                    <div class="mt-6 p-4 rounded-xl bg-gradient-to-br from-yellow-900/40 to-yellow-600/10 border border-yellow-500/30 text-center relative overflow-hidden group">
                        <div class="absolute -right-4 -top-4 w-16 h-16 bg-yellow-500/20 rounded-full blur-xl group-hover:bg-yellow-500/30 transition duration-500"></div>
                        <i class="fas fa-crown text-yellow-500 text-2xl mb-2 relative z-10"></i>
                        <h4 class="text-white font-bold text-sm relative z-10">Become an Agent</h4>
                        <p class="text-[10px] text-yellow-200/70 mt-1 mb-3 relative z-10">Unlock wholesale prices instantly.</p>
                        <a href="index.php?module=user&page=agent" class="inline-block w-full bg-yellow-500 hover:bg-yellow-400 text-slate-900 font-bold py-2 rounded-lg text-xs uppercase tracking-wider transition shadow-lg relative z-10">Learn More</a>
                    </div>
                <?php endif; ?>
            </div>
            
        </div>

        <!-- ========================================== -->
        <!-- RIGHT MAIN: Hero & Products                -->
        <!-- ========================================== -->
        <div class="lg:col-span-3 space-y-6">
            
            <!-- Dynamic Hero -->
            <div class="relative rounded-2xl md:rounded-3xl overflow-hidden border border-[#00f0ff]/20 shadow-[0_10px_30px_rgba(0,0,0,0.5)] bg-slate-900/80 backdrop-blur-xl">
                <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PHBhdHRlcm4gaWQ9ImdyaWQiIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCIgcGF0dGVyblVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+PHBhdGggZD0iTSAwIDEwIEwgNDAgMTAgTSAxMCAwIEwgMTAgNDAiIGZpbGw9Im5vbmUiIHN0cm9rZT0icmdiYSgwLCAyNDAsIDI1NSwgMC4wNSkiIHN0cm9rZS13aWR0aD0iMSIvPjwvcGF0dGVybj48L2RlZnM+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0idXJsKCNncmlkKSIvPjwvc3ZnPg==')] opacity-50"></div>
                <div class="absolute -right-10 -top-10 md:-right-20 md:-top-20 w-40 h-40 md:w-64 md:h-64 bg-[#00f0ff]/10 rounded-full blur-3xl pointer-events-none"></div>
                
                <div class="relative p-5 md:p-10 flex flex-col sm:flex-row items-center sm:items-start gap-4 md:gap-6 text-center sm:text-left">
                    <div class="w-16 h-16 md:w-20 md:h-20 bg-slate-900 rounded-xl md:rounded-2xl flex items-center justify-center shadow-[0_0_20px_rgba(0,240,255,0.2)] border border-[#00f0ff]/40 shrink-0 relative overflow-hidden">
                        <div class="absolute inset-0 bg-gradient-to-br from-blue-600/20 to-[#00f0ff]/20"></div>
                        <i class="fas <?php echo htmlspecialchars($current_category['icon_class']); ?> text-3xl md:text-4xl text-[#00f0ff] relative z-10 drop-shadow-[0_0_10px_rgba(0,240,255,0.8)]"></i>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-center sm:justify-start gap-2 md:gap-3 mb-2">
                            <span class="text-[8px] md:text-[9px] font-black text-slate-500 uppercase tracking-widest bg-slate-950/50 px-2 py-0.5 rounded border border-slate-700/50">
                                <?php echo htmlspecialchars($current_category['type']); ?> Matrix
                            </span>
                            <span class="text-[8px] md:text-[9px] font-black text-green-400 uppercase tracking-widest bg-green-500/10 px-2 py-0.5 rounded border border-green-500/30 flex items-center gap-1">
                                <span class="w-1 h-1 md:w-1.5 md:h-1.5 rounded-full bg-green-500 animate-pulse"></span> Online
                            </span>
                        </div>
                        <h1 class="text-2xl md:text-4xl font-black text-white mb-1 md:mb-2 tracking-tight"><?php echo htmlspecialchars($current_category['name']); ?></h1>
                        <p class="text-slate-400 text-xs md:text-sm leading-relaxed max-w-2xl hidden sm:block"><?php echo htmlspecialchars($current_category['description']); ?></p>
                    </div>
                </div>
            </div>

            <!-- Global View Extras: Trending & Recommendations -->
            <?php if ($cat_id == 0): ?>
                
                <?php if(!empty($trending_products)): ?>
                    <div class="mb-6 md:mb-8 pt-2 md:pt-4">
                        <div class="flex items-center gap-2 md:gap-3 mb-4 md:mb-6">
                            <div class="w-8 h-8 md:w-10 md:h-10 rounded-lg md:rounded-xl bg-orange-500/10 flex items-center justify-center border border-orange-500/20">
                                <i class="fas fa-fire text-orange-500 text-base md:text-lg"></i>
                            </div>
                            <h3 class="text-lg md:text-xl font-black text-white tracking-tight">Trending Nodes</h3>
                        </div>
                        <!-- Scrollable horizontally on mobile, grid on desktop -->
                        <div class="flex overflow-x-auto pb-4 -mx-4 px-4 sm:mx-0 sm:px-0 sm:grid sm:grid-cols-2 xl:grid-cols-3 gap-4 md:gap-6 relative z-10 hide-scrollbar snap-x">
                            <?php foreach($trending_products as $product): ?>
                                <div class="min-w-[260px] sm:min-w-0 snap-center">
                                    <?php include __DIR__ . '/../home/product_card.php'; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if(!empty($recommended_products)): ?>
                    <div class="mb-6 md:mb-10 border-t border-slate-700/50 pt-6 md:pt-8">
                        <div class="flex items-center gap-2 md:gap-3 mb-4 md:mb-6">
                            <div class="w-8 h-8 md:w-10 md:h-10 rounded-lg md:rounded-xl bg-yellow-500/10 flex items-center justify-center border border-yellow-500/20">
                                <i class="fas fa-star text-yellow-400 text-base md:text-lg"></i>
                            </div>
                            <h3 class="text-lg md:text-xl font-black text-white tracking-tight">Recommended Deals</h3>
                        </div>
                        <div class="flex overflow-x-auto pb-4 -mx-4 px-4 sm:mx-0 sm:px-0 sm:grid sm:grid-cols-2 xl:grid-cols-3 gap-4 md:gap-6 relative z-10 hide-scrollbar snap-x">
                            <?php foreach($recommended_products as $product): ?>
                                <div class="min-w-[260px] sm:min-w-0 snap-center">
                                    <?php include __DIR__ . '/../home/product_card.php'; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Separator before the main grid -->
                <div class="flex items-center gap-2 md:gap-3 mb-4 md:mb-6 border-t border-slate-700/50 pt-6 md:pt-8">
                    <div class="w-8 h-8 md:w-10 md:h-10 rounded-lg md:rounded-xl bg-[#00f0ff]/10 flex items-center justify-center border border-[#00f0ff]/20">
                        <i class="fas fa-layer-group text-[#00f0ff] text-base md:text-lg"></i>
                    </div>
                    <h3 class="text-lg md:text-xl font-black text-white tracking-tight">All Active Deployments</h3>
                </div>

            <?php endif; ?>

            <!-- Toolbar (Sorting & Meta) -->
            <div class="bg-slate-900/60 backdrop-blur-md border border-slate-700/50 rounded-xl p-2.5 md:p-3 flex justify-between items-center z-10 relative">
                <p class="text-[10px] md:text-xs text-slate-400 font-medium">
                    <span class="text-white font-bold"><?php echo count($products); ?></span> items
                </p>
                
                <div class="flex items-center gap-2">
                    <label class="hidden sm:inline text-[10px] text-slate-500 font-bold uppercase tracking-wider">Sort:</label>
                    <select onchange="window.location.href=this.value" class="bg-slate-800 border border-slate-600 rounded-lg px-2 py-1 md:px-3 md:py-1.5 text-[10px] md:text-xs text-white focus:border-[#00f0ff] outline-none cursor-pointer appearance-none pr-6 relative">
                        <option value="index.php?module=shop&page=category<?php echo $cat_id ? '&id='.$cat_id : ''; ?>&sort=newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest</option>
                        <option value="index.php?module=shop&page=category<?php echo $cat_id ? '&id='.$cat_id : ''; ?>&sort=price_asc" <?php echo $sort == 'price_asc' ? 'selected' : ''; ?>>Price (Low-High)</option>
                        <option value="index.php?module=shop&page=category<?php echo $cat_id ? '&id='.$cat_id : ''; ?>&sort=price_desc" <?php echo $sort == 'price_desc' ? 'selected' : ''; ?>>Price (High-Low)</option>
                        <option value="index.php?module=shop&page=category<?php echo $cat_id ? '&id='.$cat_id : ''; ?>&sort=name_asc" <?php echo $sort == 'name_asc' ? 'selected' : ''; ?>>A-Z</option>
                    </select>
                </div>
            </div>

            <!-- Main Product Grid -->
            <?php if (empty($products)): ?>
                <div class="glass p-8 md:p-12 rounded-2xl md:rounded-3xl text-center border border-slate-700/50 bg-slate-900/60 backdrop-blur-xl shadow-2xl relative z-10">
                    <div class="w-16 h-16 md:w-24 md:h-24 bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-4 md:mb-6 shadow-inner border border-slate-700">
                        <i class="fas fa-ghost text-3xl md:text-5xl text-slate-600"></i>
                    </div>
                    <h3 class="text-xl md:text-2xl font-black text-white mb-2 tracking-tight">Sector Empty</h3>
                    <p class="text-slate-400 max-w-sm mx-auto mb-6 md:mb-8 text-xs md:text-sm leading-relaxed">No digital assets are currently active in this view.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 md:gap-6 relative z-10">
                    <?php foreach($products as $product): ?>
                        <!-- Reusing the polished product card component -->
                        <?php include __DIR__ . '/../home/product_card.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
    // Mobile Sidebar Toggle Logic
    const toggleBtn = document.getElementById('mobileSidebarToggle');
    const sidebar = document.getElementById('categorySidebar');
    const closeBtn = document.getElementById('closeSidebarBtn');

    if (toggleBtn && sidebar && closeBtn) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.remove('hidden');
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        });

        closeBtn.addEventListener('click', () => {
            sidebar.classList.add('hidden');
            document.body.style.overflow = '';
        });
    }
</script>