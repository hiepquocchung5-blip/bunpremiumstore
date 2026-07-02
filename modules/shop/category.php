<?php
// modules/shop/category.php
// PRODUCTION v4.0 - Added Pagination, Region Filtering & Query Consolidation

// 1. Core Variables & Request Parameters
$cat_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$region_filter = isset($_GET['region']) ? (int)$_GET['region'] : 0;
$available_filter = isset($_GET['available']) ? (int)$_GET['available'] : 0;
$layout = ($_GET['layout'] ?? '') === 'list' ? 'list' : 'grid';
$current_page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$items_per_page = 12;
$offset = ($current_page - 1) * $items_per_page;

$trending_products = [];
$recommended_products = [];

// 2. Fetch Active Category Details or Fallback to Global
if ($cat_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$cat_id]);
    $current_category = $stmt->fetch();

    if (!$current_category) {
        echo "
        <div class='flex flex-col items-center justify-center min-h-[60vh] text-center px-4 relative'>
            <i class='fas fa-exclamation-circle text-7xl mb-6 text-rose-500 relative z-10'></i>
            <h2 class='text-3xl font-bold text-white mb-4 relative z-10'>Category Not Found</h2>
            <p class='text-slate-400 max-w-md mb-8 relative z-10'>The category you are looking for does not exist or has been removed.</p>
            <a href='index.php' class='bg-blue-600 hover:bg-blue-500 text-white font-bold px-8 py-4 rounded-2xl transition-all shadow-lg active:scale-95'>
                Return to Home
            </a>
        </div>";
        return;
    }
} else {
    $current_category = [
        'id' => 0,
        'name' => 'All Products',
        'type' => 'Store',
        'image_url' => null,
        'description' => 'Browse our complete catalog of digital goods, subscriptions, and tools.'
    ];
}

// 3. Fetch Sidebar Data (Categories & Regions)
$all_categories = $pdo->query("
    SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND " . product_active_condition('p') . ") as count 
    FROM categories c ORDER BY c.name ASC
")->fetchAll();

$all_regions = $pdo->query("SELECT * FROM regions ORDER BY name ASC")->fetchAll();

// 4. Dynamic Query Builder for Products
$where_clauses = [];
$params = [];
$where_clauses[] = product_active_condition('p');

if ($cat_id > 0) {
    $where_clauses[] = "p.category_id = ?";
    $params[] = $cat_id;
}
if ($region_filter > 0) {
    $where_clauses[] = "p.region_id = ?";
    $params[] = $region_filter;
}
if ($available_filter === 1) {
    $where_clauses[] = "(p.delivery_type != 'unique' OR EXISTS (SELECT 1 FROM product_keys pk WHERE pk.product_id = p.id AND pk.is_sold = 0 AND pk.order_id IS NULL))";
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// Sorting Logic
$order_sql = "ORDER BY p.id DESC";
switch ($sort) {
    case 'price_asc':  $order_sql = "ORDER BY COALESCE(p.sale_price, p.price) ASC"; break;
    case 'price_desc': $order_sql = "ORDER BY COALESCE(p.sale_price, p.price) DESC"; break;
    case 'name_asc':   $order_sql = "ORDER BY p.name ASC"; break;
}

// 5. Execute Queries (Total Count + Paged Items)
$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM products p $where_sql");
$stmt_count->execute($params);
$total_items = $stmt_count->fetchColumn();
$total_pages = ceil($total_items / $items_per_page);

$stmt = $pdo->prepare("
    SELECT p.*, c.name as cat_name, c.image_url as cat_image,
           (SELECT COUNT(*) FROM product_keys pk WHERE pk.product_id = p.id AND pk.is_sold = 0 AND pk.order_id IS NULL) as stock_count,
           (SELECT COUNT(*) FROM orders o WHERE o.product_id = p.id AND o.status = 'active') as sales_count
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    $where_sql 
    $order_sql 
    LIMIT $items_per_page OFFSET $offset
");
$stmt->execute($params);
$products = $stmt->fetchAll();

// 6. Fetch "Extras" ONLY on Global Hub (Page 1, No Filters)
if ($cat_id == 0 && $current_page == 1 && $region_filter == 0) {
    $trending_products = $pdo->query("
        SELECT p.*, c.name as cat_name, c.image_url as cat_image, COUNT(o.id) as sales_count,
               (SELECT COUNT(*) FROM product_keys pk WHERE pk.product_id = p.id AND pk.is_sold = 0 AND pk.order_id IS NULL) as stock_count
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        LEFT JOIN orders o ON p.id = o.product_id AND o.status = 'active'
        WHERE " . product_active_condition('p') . "
        GROUP BY p.id ORDER BY sales_count DESC LIMIT 3
    ")->fetchAll();

    $recommended_products = $pdo->query("
        SELECT p.*, c.name as cat_name, c.image_url as cat_image,
               (SELECT COUNT(*) FROM product_keys pk WHERE pk.product_id = p.id AND pk.is_sold = 0 AND pk.order_id IS NULL) as stock_count,
               (SELECT COUNT(*) FROM orders o WHERE o.product_id = p.id AND o.status = 'active') as sales_count
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        WHERE p.sale_price IS NOT NULL AND p.sale_price < p.price AND " . product_active_condition('p') . "
        ORDER BY RAND() LIMIT 3
    ")->fetchAll();
}

// 7. User Discount
$discount = is_logged_in() ? get_user_discount($_SESSION['user_id']) : 0;

// Helper to build pagination links preserving filters
function get_page_url($page_num) {
    $params = $_GET;
    $params['p'] = $page_num;
    return 'index.php?' . http_build_query($params);
}
?>

<style>
    .hide-scrollbar::-webkit-scrollbar { display: none; }
    .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

    /* Layout transition swap styles */
    #productsGridContainer.list-view,
    #skeletonGridContainer.list-view {
        grid-template-columns: repeat(1, minmax(0, 1fr)) !important;
    }
    
    @keyframes cardSwapAnim {
        from {
            opacity: 0;
            transform: scale(0.97) translateY(8px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }
    
    .swap-active .dm-card {
        animation: cardSwapAnim 0.35s cubic-bezier(0.16, 1, 0.3, 1) both;
    }
    
    @media (min-width: 640px) {
        #productsGridContainer.list-view .dm-card,
        #skeletonGridContainer.list-view .dm-card {
            flex-direction: row !important;
            align-items: stretch;
            height: auto;
        }
        #productsGridContainer.list-view .dm-card > div:first-child,
        #skeletonGridContainer.list-view .dm-card > div:first-child {
            width: 220px !important;
            height: auto !important;
            aspect-ratio: auto !important;
            flex-shrink: 0;
        }
        #productsGridContainer.list-view .dm-card > div:nth-child(2),
        #skeletonGridContainer.list-view .dm-card > div:nth-child(2) {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
    }

    /* Pulse and Fade animations */
    @keyframes pulse-fast {
        0%, 100% { opacity: 1; }
        50% { opacity: .4; }
    }
    .animate-pulse-fast {
        animation: pulse-fast 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
    .fade-in {
        animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<!-- Background -->
<div class="fixed inset-0 w-full h-full dm-gradient-bg -z-20"></div>
<div class="fixed top-0 left-0 w-full h-full -z-10 opacity-20 pointer-events-none">
    <div class="absolute top-[-10%] left-[-10%] w-[50%] h-[50%] bg-blue-600/20 rounded-full blur-[120px]"></div>
</div>

<div class="max-w-7xl mx-auto px-6 relative z-0 pb-16 pt-8">
    
    <!-- Breadcrumb Navigation -->
    <div class="mb-8 flex items-center justify-between">
        <div class="flex items-center gap-3 text-xs font-bold text-slate-500">
            <a href="index.php" class="hover:text-blue-400 transition"><i class="fas fa-home"></i></a>
            <i class="fas fa-chevron-right text-[8px] opacity-50"></i>
            <span class="text-blue-400 truncate max-w-[200px] md:max-w-none"><?php echo htmlspecialchars($current_category['name']); ?></span>
        </div>
        
        <button id="mobileSidebarToggle" class="lg:hidden bg-slate-800/50 border border-white/5 text-white px-4 py-2 rounded-xl text-xs font-bold flex items-center gap-2 hover:bg-slate-800 transition">
            <i class="fas fa-filter"></i> Filters
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        
        <!-- LEFT SIDEBAR -->
        <div id="categorySidebar" class="hidden lg:block lg:col-span-1 z-40 fixed inset-0 lg:static bg-slate-950/90 backdrop-blur-md lg:bg-transparent lg:backdrop-blur-none overflow-y-auto lg:overflow-visible hide-scrollbar pt-24 lg:pt-0 px-6 lg:px-0">
            
            <div class="bg-slate-800/20 border border-white/5 rounded-[2rem] p-6 lg:sticky lg:top-24 max-w-sm mx-auto lg:max-w-none shadow-xl">
                <div class="flex items-center justify-between mb-6 border-b border-white/5 pb-4">
                    <h3 class="font-bold text-white text-sm uppercase tracking-widest">
                        Categories
                    </h3>
                    <button id="closeSidebarBtn" class="lg:hidden text-slate-500 hover:text-white transition"><i class="fas fa-times text-xl"></i></button>
                </div>
                
                <ul class="space-y-2">
                    <li>
                        <a href="index.php?module=shop&page=category" class="flex items-center justify-between p-3 rounded-xl transition-all <?php echo $cat_id == 0 ? 'bg-blue-600 text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white'; ?>">
                            <span class="font-bold text-sm">All Products</span>
                        </a>
                    </li>
                    <?php foreach($all_categories as $cat): ?>
                        <li>
                            <a href="index.php?module=shop&page=category&id=<?php echo $cat['id']; ?>" class="flex items-center justify-between p-3 rounded-xl transition-all <?php echo $cat['id'] == $cat_id ? 'bg-blue-600 text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white'; ?>">
                                <span class="font-bold text-sm"><?php echo htmlspecialchars($cat['name']); ?></span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full <?php echo $cat['id'] == $cat_id ? 'bg-white/20 text-white' : 'bg-slate-800/50 text-slate-500'; ?>"><?php echo $cat['count']; ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- RIGHT MAIN -->
        <div class="lg:col-span-3 space-y-8">
            
            <!-- Category Hero Header Banner -->
            <div class="relative rounded-[2.5rem] overflow-hidden border border-white/5 shadow-2xl bg-slate-950 min-h-[220px] flex items-end p-8 md:p-12">
                <?php if(!empty($current_category['image_url'])): ?>
                    <img src="<?php echo BASE_URL . $current_category['image_url']; ?>" alt="<?php echo htmlspecialchars($current_category['name']); ?>" class="absolute inset-0 w-full h-full object-cover opacity-25 filter blur-[2px] transition-transform duration-700 hover:scale-105" loading="lazy">
                <?php endif; ?>
                <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/60 to-transparent"></div>
                <div class="relative z-10 max-w-3xl">
                    <span class="text-[9px] font-bold text-blue-400 uppercase tracking-[0.25em] bg-blue-500/10 px-3.5 py-1 rounded-full border border-blue-500/20">Category Hub</span>
                    <h1 class="text-3xl md:text-5xl font-black text-white tracking-tight mt-3 leading-tight"><?php echo htmlspecialchars($current_category['name']); ?></h1>
                    <p class="text-slate-400 text-xs md:text-sm leading-relaxed font-medium mt-2 max-w-2xl"><?php echo htmlspecialchars($current_category['description']); ?></p>
                </div>
            </div>

            <!-- Global View Extras -->
            <?php if (!empty($trending_products)): ?>
                <div class="mb-12">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 rounded-xl bg-orange-500/10 flex items-center justify-center border border-orange-500/20"><i class="fas fa-fire text-orange-500 text-lg"></i></div>
                        <h3 class="text-xl font-bold text-white tracking-tight">Trending Items</h3>
                    </div>
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
                        <?php foreach($trending_products as $product): ?>
                            <?php include __DIR__ . '/../home/product_card.php'; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if(!empty($recommended_products)): ?>
                <div class="mb-12 border-t border-white/5 pt-8">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 rounded-xl bg-yellow-500/10 flex items-center justify-center border border-yellow-500/20"><i class="fas fa-star text-yellow-400 text-lg"></i></div>
                        <h3 class="text-xl font-bold text-white tracking-tight">Recommended Deals</h3>
                    </div>
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
                        <?php foreach($recommended_products as $product): ?>
                            <?php include __DIR__ . '/../home/product_card.php'; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="flex items-center gap-3 mb-8 border-t border-white/5 pt-8">
                    <div class="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center border border-blue-500/20"><i class="fas fa-layer-group text-blue-400 text-lg"></i></div>
                    <h3 class="text-xl font-bold text-white tracking-tight">All Products</h3>
                </div>
            <?php endif; ?>

            <!-- Toolbar (Sorting, Filters & Meta) -->
            <div class="bg-slate-800/20 border border-white/5 rounded-2xl p-4 flex flex-col md:flex-row justify-between items-center gap-4 z-10 relative">
                <div class="flex items-center gap-4 w-full md:w-auto justify-between md:justify-start">
                    <p class="text-xs text-slate-400 font-medium">
                        Showing <span class="text-white font-bold"><?php echo min($total_items, $offset + 1); ?> - <?php echo min($total_items, $offset + $items_per_page); ?></span> of <span class="text-blue-400 font-bold"><?php echo $total_items; ?></span>
                    </p>
                    
                    <!-- Layout Toggle Buttons -->
                    <div class="flex items-center gap-1 bg-slate-900/60 p-1 rounded-xl border border-white/5">
                        <button id="gridLayoutBtn" type="button" class="w-8 h-8 rounded-lg flex items-center justify-center text-xs transition-all" title="Grid View">
                            <i class="fas fa-th-large"></i>
                        </button>
                        <button id="listLayoutBtn" type="button" class="w-8 h-8 rounded-lg flex items-center justify-center text-xs transition-all" title="List View">
                            <i class="fas fa-list"></i>
                        </button>
                    </div>
                </div>
                
                <form id="filterForm" method="GET" class="flex flex-wrap items-center justify-center gap-3 w-full md:w-auto">
                    <input type="hidden" name="module" value="shop">
                    <input type="hidden" name="page" value="category">
                    <?php if($cat_id): ?><input type="hidden" name="id" value="<?php echo $cat_id; ?>"><?php endif; ?>
                    <input type="hidden" name="layout" id="layoutInput" value="<?php echo htmlspecialchars($layout); ?>">
                    
                    <select name="region" onchange="document.getElementById('filterForm').submit()" class="bg-slate-900 border border-white/10 rounded-xl px-4 py-2 text-xs text-white focus:border-blue-500 outline-none cursor-pointer appearance-none">
                        <option value="0">All Regions</option>
                        <?php foreach($all_regions as $r): ?>
                            <option value="<?php echo $r['id']; ?>" <?php echo $region_filter == $r['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($r['name']); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select name="available" onchange="document.getElementById('filterForm').submit()" class="bg-slate-900 border border-white/10 rounded-xl px-4 py-2 text-xs text-white focus:border-blue-500 outline-none cursor-pointer appearance-none">
                        <option value="0" <?php echo $available_filter === 0 ? 'selected' : ''; ?>>All Stock</option>
                        <option value="1" <?php echo $available_filter === 1 ? 'selected' : ''; ?>>Available Now</option>
                    </select>

                    <select name="sort" onchange="document.getElementById('filterForm').submit()" class="bg-slate-900 border border-white/10 rounded-xl px-4 py-2 text-xs text-white focus:border-blue-500 outline-none cursor-pointer appearance-none">
                        <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="price_asc" <?php echo $sort == 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_desc" <?php echo $sort == 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="name_asc" <?php echo $sort == 'name_asc' ? 'selected' : ''; ?>>Name: A to Z</option>
                    </select>
                </form>
            </div>

            <!-- Main Product Grid -->
            <?php if (empty($products)): ?>
                <div class="bg-slate-800/20 p-12 md:p-20 rounded-[2.5rem] text-center border border-white/5 shadow-2xl relative z-10">
                    <div class="w-24 h-24 bg-slate-900 rounded-full flex items-center justify-center mx-auto mb-6 shadow-inner border border-white/10 text-slate-700">
                        <i class="fas fa-box-open text-4xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-3 tracking-tight">No Products Found</h3>
                    <p class="text-slate-500 max-w-sm mx-auto mb-8 text-sm leading-relaxed">There are currently no items available in this category with the selected filters.</p>
                </div>
            <?php else: ?>
                <!-- Skeleton Grid Loader -->
                <div id="skeletonGridContainer" class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 relative z-10">
                    <?php for ($i = 0; $i < 8; $i++): ?>
                        <div class="dm-card rounded-[1.75rem] overflow-hidden flex flex-col h-full relative shadow-[0_12px_40px_rgba(0,0,0,0.18)] bg-slate-900/30 border border-white/5 p-4 sm:p-5 space-y-4">
                            <div class="prod-img-wrap bg-slate-800/50 animate-pulse-fast"></div>
                            <div class="p-4 sm:p-5 flex-grow flex flex-col space-y-3">
                                <div class="w-1/3 h-3 bg-slate-800/50 rounded-full animate-pulse-fast"></div>
                                <div class="w-3/4 h-5 bg-slate-800/50 rounded-full animate-pulse-fast"></div>
                                <div class="w-5/6 h-3 bg-slate-800/50 rounded-full animate-pulse-fast"></div>
                                <div class="w-2/3 h-3 bg-slate-800/50 rounded-full animate-pulse-fast mb-2"></div>
                                <div class="pt-4 border-t border-white/5 flex items-center justify-between mt-auto">
                                    <div class="space-y-2">
                                        <div class="w-20 h-6 bg-slate-800/50 rounded-full animate-pulse-fast"></div>
                                        <div class="w-12 h-3.5 bg-slate-800/50 rounded-full animate-pulse-fast"></div>
                                    </div>
                                    <div class="w-9 h-9 rounded-full bg-slate-800/50 animate-pulse-fast"></div>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>

                <div id="productsGridContainer" class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 relative z-10 transition-all duration-300" style="display: none;">
                    <?php foreach($products as $product): ?>
                        <?php include __DIR__ . '/../home/product_card.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="flex justify-center items-center gap-3 mt-16 relative z-10">
                    <?php if ($current_page > 1): ?>
                        <a href="<?php echo get_page_url($current_page - 1); ?>" class="w-12 h-12 bg-slate-800/50 hover:bg-blue-600 border border-white/5 hover:border-transparent rounded-2xl flex items-center justify-center text-white transition-all shadow-lg"><i class="fas fa-chevron-left"></i></a>
                    <?php endif; ?>
                    
                    <div class="hidden sm:flex gap-2">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == 1 || $i == $total_pages || ($i >= $current_page - 1 && $i <= $current_page + 1)): ?>
                                <a href="<?php echo get_page_url($i); ?>" class="w-12 h-12 <?php echo $i == $current_page ? 'bg-blue-600 text-white font-bold shadow-lg shadow-blue-500/20' : 'bg-slate-800/50 hover:bg-slate-700 text-slate-400 hover:text-white border border-white/5'; ?> rounded-2xl flex items-center justify-center transition-all text-sm"><?php echo $i; ?></a>
                            <?php elseif ($i == $current_page - 2 || $i == $current_page + 2): ?>
                                <span class="w-12 h-12 flex items-center justify-center text-slate-600">...</span>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    
                    <div class="sm:hidden flex items-center px-4 text-sm font-bold text-slate-400">
                        Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
                    </div>

                    <?php if ($current_page < $total_pages): ?>
                        <a href="<?php echo get_page_url($current_page + 1); ?>" class="w-12 h-12 bg-slate-800/50 hover:bg-blue-600 border border-white/5 hover:border-transparent rounded-2xl flex items-center justify-center text-white transition-all shadow-lg"><i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
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
            document.body.style.overflow = 'hidden'; 
        });

        closeBtn.addEventListener('click', () => {
            sidebar.classList.add('hidden');
            document.body.style.overflow = '';
        });
    }

    // Layout Switcher Logic
    const gridLayoutBtn = document.getElementById('gridLayoutBtn');
    const listLayoutBtn = document.getElementById('listLayoutBtn');
    const productsGridContainer = document.getElementById('productsGridContainer');
    const skeletonGridContainer = document.getElementById('skeletonGridContainer');

    if (gridLayoutBtn && listLayoutBtn && productsGridContainer) {
        const setLayout = (layout) => {
            const layoutInput = document.getElementById('layoutInput');
            if (layoutInput) layoutInput.value = layout;
            productsGridContainer.classList.add('swap-active');
            if (skeletonGridContainer) {
                skeletonGridContainer.classList.add('swap-active');
            }
            
            if (layout === 'list') {
                productsGridContainer.classList.add('list-view');
                if (skeletonGridContainer) {
                    skeletonGridContainer.classList.add('list-view');
                }
                listLayoutBtn.className = "w-8 h-8 rounded-lg flex items-center justify-center text-xs transition-all text-blue-400 bg-slate-800/80 border border-white/10";
                gridLayoutBtn.className = "w-8 h-8 rounded-lg flex items-center justify-center text-xs transition-all text-slate-400 hover:text-white";
            } else {
                productsGridContainer.classList.remove('list-view');
                if (skeletonGridContainer) {
                    skeletonGridContainer.classList.remove('list-view');
                }
                gridLayoutBtn.className = "w-8 h-8 rounded-lg flex items-center justify-center text-xs transition-all text-blue-400 bg-slate-800/80 border border-white/10";
                listLayoutBtn.className = "w-8 h-8 rounded-lg flex items-center justify-center text-xs transition-all text-slate-400 hover:text-white";
            }
            
            localStorage.setItem('category_layout', layout);
            const url = new URL(window.location.href);
            url.searchParams.set('layout', layout);
            window.history.replaceState({}, '', url.toString());
            
            setTimeout(() => {
                productsGridContainer.classList.remove('swap-active');
                if (skeletonGridContainer) {
                    skeletonGridContainer.classList.remove('swap-active');
                }
            }, 350);
        };

        // Initialize from localStorage
        const urlLayout = new URLSearchParams(window.location.search).get('layout');
        const currentLayout = urlLayout || localStorage.getItem('category_layout') || <?php echo json_encode($layout); ?>;
        setLayout(currentLayout);

        gridLayoutBtn.addEventListener('click', () => setLayout('grid'));
        listLayoutBtn.addEventListener('click', () => setLayout('list'));
    }

    // Hide skeleton and reveal products with fade-in on load
    const showProducts = () => {
        const skeleton = document.getElementById('skeletonGridContainer');
        const products = document.getElementById('productsGridContainer');
        if (skeleton) {
            skeleton.style.display = 'none';
        }
        if (products) {
            products.style.display = 'grid';
            products.classList.add('fade-in');
        }
    };
    if (document.readyState === 'complete') {
        showProducts();
    } else {
        window.addEventListener('load', showProducts);
    }
</script>
