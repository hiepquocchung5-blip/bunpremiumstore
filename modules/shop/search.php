<?php
// modules/shop/search.php
// PRODUCTION v3.0 - Autocomplete, Filter Bar, and Suggested Categories

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$cat_filter = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
$min_price = isset($_GET['min_p']) ? (float)$_GET['min_p'] : 0.0;
$max_price = isset($_GET['max_p']) ? (float)$_GET['max_p'] : 0.0;
$delivery_filter = isset($_GET['delivery']) ? trim($_GET['delivery']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'newest';
$available_filter = isset($_GET['available']) ? (int)$_GET['available'] : 0;

// 1. Fetch categories for filters and suggestions
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

// 2. Perform Dynamic Search
$where = [product_active_condition('p'), "(p.name LIKE ? OR c.name LIKE ? OR p.description LIKE ?)"];
$params = ["%$query%", "%$query%", "%$query%"];

if ($cat_filter > 0) {
    $where[] = "p.category_id = ?";
    $params[] = $cat_filter;
}
if ($min_price > 0) {
    $where[] = "COALESCE(p.sale_price, p.price) >= ?";
    $params[] = $min_price;
}
if ($max_price > 0) {
    $where[] = "COALESCE(p.sale_price, p.price) <= ?";
    $params[] = $max_price;
}
if (in_array($delivery_filter, ['universal', 'unique', 'form'])) {
    $where[] = "p.delivery_type = ?";
    $params[] = $delivery_filter;
}
if ($available_filter === 1) {
    $where[] = "(p.delivery_type != 'unique' OR EXISTS (SELECT 1 FROM product_keys pk WHERE pk.product_id = p.id AND pk.is_sold = 0 AND pk.order_id IS NULL))";
}

$where_sql = implode(" AND ", $where);

$order_sql = "ORDER BY p.id DESC";
if ($sort === 'price_asc') {
    $order_sql = "ORDER BY COALESCE(p.sale_price, p.price) ASC";
} elseif ($sort === 'price_desc') {
    $order_sql = "ORDER BY COALESCE(p.sale_price, p.price) DESC";
} elseif ($sort === 'best') {
    $order_sql = "ORDER BY (SELECT COUNT(*) FROM orders WHERE product_id = p.id AND status = 'active') DESC, p.id DESC";
} elseif ($sort === 'sale') {
    // Only items with sales
    $where_sql .= " AND p.sale_price IS NOT NULL AND p.sale_price < p.price";
}

$stmt = $pdo->prepare("
    SELECT p.*, c.name as cat_name, c.image_url as cat_image,
           (SELECT COUNT(*) FROM product_keys pk WHERE pk.product_id = p.id AND pk.is_sold = 0 AND pk.order_id IS NULL) as stock_count,
           (SELECT COUNT(*) FROM orders o WHERE o.product_id = p.id AND o.status = 'active') as sales_count
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    WHERE $where_sql
    $order_sql
");
$stmt->execute($params);
$results = $stmt->fetchAll();

// 3. Get Agent Discount
$discount = is_logged_in() ? get_user_discount($_SESSION['user_id']) : 0;
?>

<style>
    /* Suggestions Dropdown position and design */
    #suggestionsDropdown {
        border-radius: 1rem;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    }
</style>

<!-- Background Effects -->
<div class="fixed inset-0 w-full h-full dm-gradient-bg -z-20"></div>
<div class="fixed top-0 left-0 w-full h-full -z-10 opacity-20 pointer-events-none">
    <div class="absolute top-[-10%] left-[-10%] w-[50%] h-[50%] bg-blue-600/20 rounded-full blur-[120px]"></div>
</div>

<div class="max-w-7xl mx-auto px-6 pb-20 relative z-10">

    <!-- Search Header -->
    <div class="mb-12 text-center pt-12 md:pt-16">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-slate-800/80 rounded-[1.5rem] border border-white/5 shadow-xl mb-6 text-blue-400 text-2xl">
            <i class="fas fa-search"></i>
        </div>
        
        <h1 class="text-3xl md:text-5xl font-bold text-white mb-4 tracking-tight">Search Catalog</h1>
        <p class="text-slate-500 font-medium">
            Found <span class="text-white font-bold mx-1"><?php echo count($results); ?></span> items for <span class="text-blue-400 font-bold italic">"<?php echo htmlspecialchars($query); ?>"</span>
        </p>
    </div>

    <!-- Search Input & Autocomplete Dropdown -->
    <div class="max-w-2xl mx-auto mb-8 relative">
        <form action="index.php" method="GET" class="relative group" id="searchForm">
            <input type="hidden" name="module" value="shop">
            <input type="hidden" name="page" value="search">
            
            <input type="text" name="q" id="searchInput" value="<?php echo htmlspecialchars($query); ?>" placeholder="Type to search..." autocomplete="off"
                   class="w-full bg-slate-800/40 border border-white/5 focus:border-blue-500/50 rounded-2xl py-5 pl-6 pr-32 text-white placeholder-slate-600 outline-none shadow-2xl backdrop-blur-xl transition-all">
            
            <button type="submit" class="absolute right-2 top-2 bottom-2 bg-blue-600 hover:bg-blue-500 text-white font-bold px-8 rounded-xl transition-all active:scale-95 text-xs uppercase tracking-widest">
                Search
            </button>
        </form>

        <!-- Suggestions Dropdown -->
        <div id="suggestionsDropdown" class="absolute left-0 right-0 mt-2 bg-slate-900/95 border border-white/10 rounded-2xl overflow-hidden hidden z-50 backdrop-blur-xl">
            <div id="suggestionsContent" class="divide-y divide-white/5 max-h-[360px] overflow-y-auto">
                <!-- Loaded via AJAX -->
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-slate-800/20 border border-white/5 rounded-3xl p-6 mb-12 backdrop-blur-xl">
        <form method="GET" action="index.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4 items-end">
            <input type="hidden" name="module" value="shop">
            <input type="hidden" name="page" value="search">
            <input type="hidden" name="q" value="<?php echo htmlspecialchars($query); ?>">

            <!-- Category -->
            <div class="space-y-2">
                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Category</label>
                <select name="cat" class="w-full bg-slate-900 border border-white/5 rounded-xl py-3 px-4 text-xs text-white outline-none focus:border-blue-500/50">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $cat_filter === (int)$cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Delivery Method -->
            <div class="space-y-2">
                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Delivery Method</label>
                <select name="delivery" class="w-full bg-slate-900 border border-white/5 rounded-xl py-3.5 px-4 text-xs text-white outline-none focus:border-blue-500/50">
                    <option value="">All Methods</option>
                    <option value="universal" <?php echo $delivery_filter === 'universal' ? 'selected' : ''; ?>>Universal (Instant)</option>
                    <option value="unique" <?php echo $delivery_filter === 'unique' ? 'selected' : ''; ?>>Key/Account (Instant)</option>
                    <option value="form" <?php echo $delivery_filter === 'form' ? 'selected' : ''; ?>>Form (Manual processing)</option>
                </select>
            </div>

            <!-- Min Price -->
            <div class="space-y-2">
                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Min Price (Ks)</label>
                <input type="number" name="min_p" value="<?php echo $min_price > 0 ? $min_price : ''; ?>" placeholder="Min Price" 
                       class="w-full bg-slate-900 border border-white/5 rounded-xl py-3 px-4 text-xs text-white outline-none focus:border-blue-500/50">
            </div>
            
            <!-- Max Price -->
            <div class="space-y-2">
                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Max Price (Ks)</label>
                <input type="number" name="max_p" value="<?php echo $max_price > 0 ? $max_price : ''; ?>" placeholder="Max Price" 
                       class="w-full bg-slate-900 border border-white/5 rounded-xl py-3 px-4 text-xs text-white outline-none focus:border-blue-500/50">
            </div>

            <!-- Availability -->
            <div class="space-y-2">
                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Stock</label>
                <select name="available" class="w-full bg-slate-900 border border-white/5 rounded-xl py-3.5 px-4 text-xs text-white outline-none focus:border-blue-500/50">
                    <option value="0" <?php echo $available_filter === 0 ? 'selected' : ''; ?>>All Stock</option>
                    <option value="1" <?php echo $available_filter === 1 ? 'selected' : ''; ?>>Available Now</option>
                </select>
            </div>

            <!-- Sort By -->
            <div class="space-y-2">
                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Sort By</label>
                <div class="grid grid-cols-2 gap-2">
                    <select name="sort" class="w-full bg-slate-900 border border-white/5 rounded-xl py-3 px-3 text-[11px] text-white outline-none focus:border-blue-500/50">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest</option>
                        <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price: Low-High</option>
                        <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price: High-Low</option>
                        <option value="best" <?php echo $sort === 'best' ? 'selected' : ''; ?>>Best Selling</option>
                        <option value="sale" <?php echo $sort === 'sale' ? 'selected' : ''; ?>>On Sale</option>
                    </select>
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-xl py-3 text-[10px] uppercase tracking-widest transition-all active:scale-95">
                        Filter
                    </button>
                </div>
            </div>
        </form>

        <div class="flex flex-wrap gap-2 mt-5 pt-5 border-t border-white/5">
            <?php
                $chip_base = [
                    'module' => 'shop',
                    'page' => 'search',
                    'q' => $query,
                    'cat' => $cat_filter,
                    'delivery' => $delivery_filter,
                    'sort' => $sort
                ];
                $chips = [
                    'Under 5,000 Ks' => array_merge($chip_base, ['min_p' => '', 'max_p' => 5000]),
                    '5,000-20,000 Ks' => array_merge($chip_base, ['min_p' => 5000, 'max_p' => 20000]),
                    'On Sale' => array_merge($chip_base, ['sort' => 'sale', 'min_p' => $min_price ?: '', 'max_p' => $max_price ?: '']),
                    'Available Now' => array_merge($chip_base, ['available' => 1, 'min_p' => $min_price ?: '', 'max_p' => $max_price ?: ''])
                ];
            ?>
            <?php foreach($chips as $label => $chip_params): ?>
                <a href="index.php?<?php echo http_build_query($chip_params); ?>" class="px-4 py-2 bg-slate-900/70 hover:bg-blue-600 border border-white/5 hover:border-blue-500 rounded-xl text-[11px] text-slate-300 hover:text-white font-bold transition">
                    <?php echo htmlspecialchars($label); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Results Grid -->
    <?php if (empty($results)): ?>
        <div class="bg-slate-800/20 border border-white/5 p-12 md:p-20 rounded-[2.5rem] text-center max-w-2xl mx-auto space-y-8 shadow-2xl">
            <div class="w-20 h-20 bg-slate-900 rounded-full flex items-center justify-center mx-auto text-slate-700 text-3xl">
                <i class="fas fa-search-minus"></i>
            </div>
            <div class="space-y-3">
                <h3 class="text-2xl font-bold text-white">No items found</h3>
                <p class="text-slate-500 max-w-sm mx-auto text-sm leading-relaxed">We couldn't find any products matching your search criteria. Try adjusting filters or select a category below.</p>
            </div>
            
            <!-- Suggested Categories list -->
            <div class="space-y-4 pt-4 border-t border-white/5">
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Suggested Categories</p>
                <div class="flex flex-wrap justify-center gap-2">
                    <?php foreach ($categories as $cat): ?>
                        <a href="index.php?module=shop&page=category&id=<?php echo $cat['id']; ?>" class="px-4 py-2 bg-slate-800/60 hover:bg-blue-600 border border-white/5 hover:border-blue-500 rounded-xl text-xs text-slate-300 hover:text-white transition-all">
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
            <?php foreach($results as $product): ?>
                <?php include __DIR__ . '/../home/product_card.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('searchInput');
    const dropdown = document.getElementById('suggestionsDropdown');
    const content = document.getElementById('suggestionsContent');
    let debounceTimer;
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));

    input.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        const query = input.value.trim();

        if (query.length < 2) {
            dropdown.classList.add('hidden');
            return;
        }

        debounceTimer = setTimeout(() => {
            fetch(`api/search_suggest.php?q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    content.innerHTML = '';
                    if (data && data.length > 0) {
                        data.forEach(item => {
                            const finalPrice = parseFloat(item.sale_price || item.price);
                            const priceHtml = item.sale_price ? 
                                `<span class="text-amber-400 font-bold">${finalPrice} Ks</span> <span class="text-[10px] text-slate-500 line-through block">${parseFloat(item.price)} Ks</span>` :
                                `<span class="text-white font-bold">${finalPrice} Ks</span>`;
                            const imgUrl = item.image_path ? `<?php echo BASE_URL; ?>${item.image_path}` : `<?php echo BASE_URL; ?>assets/images/og-image.png`;
                            
                            const div = document.createElement('a');
                            div.href = `index.php?module=shop&page=product&slug=${item.slug}`;
                            div.className = 'flex items-center gap-4 p-4 hover:bg-slate-800/80 transition-colors text-left border-b border-white/5';
                            div.innerHTML = `
                                <div class="w-10 h-10 rounded-lg overflow-hidden shrink-0 border border-white/5">
                                    <img src="${imgUrl}" class="w-full h-full object-cover">
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h4 class="text-white font-bold text-sm truncate">${escapeHtml(item.name)}</h4>
                                    <p class="text-[10px] text-slate-400 uppercase tracking-wider">${escapeHtml(item.cat_name)}</p>
                                </div>
                                <div class="text-right text-xs shrink-0">${priceHtml}</div>
                            `;
                            content.appendChild(div);
                        });
                        dropdown.classList.remove('hidden');
                    } else {
                        dropdown.classList.add('hidden');
                    }
                })
                .catch(() => dropdown.classList.add('hidden'));
        }, 300);
    });

    document.addEventListener('click', (e) => {
        if (!input.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });
});
</script>
