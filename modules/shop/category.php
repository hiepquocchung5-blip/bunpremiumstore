<?php
// modules/shop/category.php
// PRODUCTION v2.0 - Advanced Category Hub with Sidebar & Sorting

$cat_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// 1. Fetch Current Category Details
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

// 4. Fetch Products for Current Category
$stmt = $pdo->prepare("
    SELECT p.*, c.name as cat_name, c.icon_class 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    WHERE p.category_id = ? 
    $order_sql
");
$stmt->execute([$cat_id]);
$products = $stmt->fetchAll();

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
</style>

<!-- Animated Background -->
<div class="fixed inset-0 w-full h-full bg-slate-950 -z-20 pointer-events-none"></div>
<div class="fixed top-0 -left-4 w-96 h-96 bg-blue-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-20 animate-blob -z-10 pointer-events-none"></div>
<div class="fixed top-40 -right-4 w-96 h-96 bg-[#00f0ff] rounded-full mix-blend-multiply filter blur-[128px] opacity-10 animate-blob animation-delay-2000 -z-10 pointer-events-none"></div>

<div class="max-w-7xl mx-auto px-4 relative z-0 pb-12">
    
    <!-- Breadcrumb Navigation -->
    <div class="mb-6 flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-slate-500">
        <a href="index.php" class="hover:text-[#00f0ff] transition flex items-center gap-1.5"><i class="fas fa-home"></i> Hub</a>
        <i class="fas fa-chevron-right text-[8px] opacity-50"></i>
        <a href="index.php?module=shop&page=search" class="hover:text-[#00f0ff] transition">Store</a>
        <i class="fas fa-chevron-right text-[8px] opacity-50"></i>
        <span class="text-[#00f0ff]"><?php echo htmlspecialchars($current_category['name']); ?></span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        
        <!-- ========================================== -->
        <!-- LEFT SIDEBAR: Dynamic Category Navigation  -->
        <!-- ========================================== -->
        <div class="lg:col-span-1 space-y-6">
            
            <!-- Category Menu -->
            <div class="bg-slate-900/80 backdrop-blur-xl border border-slate-700/50 rounded-2xl p-5 shadow-xl sticky top-24">
                <h3 class="font-bold text-white mb-4 flex items-center gap-2 border-b border-slate-700/50 pb-3 text-sm uppercase tracking-widest">
                    <i class="fas fa-network-wired text-[#00f0ff]"></i> Sector Directory
                </h3>
                
                <ul class="space-y-1.5">
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
            
            <!-- Dynamic Category Hero -->
            <div class="relative rounded-3xl overflow-hidden border border-[#00f0ff]/20 shadow-[0_20px_50px_rgba(0,0,0,0.5)] bg-slate-900/80 backdrop-blur-xl">
                <!-- Tech Pattern Background -->
                <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PHBhdHRlcm4gaWQ9ImdyaWQiIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCIgcGF0dGVyblVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+PHBhdGggZD0iTSAwIDEwIEwgNDAgMTAgTSAxMCAwIEwgMTAgNDAiIGZpbGw9Im5vbmUiIHN0cm9rZT0icmdiYSgwLCAyNDAsIDI1NSwgMC4wNSkiIHN0cm9rZS13aWR0aD0iMSIvPjwvcGF0dGVybj48L2RlZnM+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0idXJsKCNncmlkKSIvPjwvc3ZnPg==')] opacity-50"></div>
                <div class="absolute -right-20 -top-20 w-64 h-64 bg-[#00f0ff]/10 rounded-full blur-3xl pointer-events-none"></div>
                
                <div class="relative p-6 md:p-10 flex flex-col md:flex-row items-start md:items-center gap-6">
                    <div class="w-20 h-20 bg-slate-900 rounded-2xl flex items-center justify-center shadow-[0_0_25px_rgba(0,240,255,0.2)] border border-[#00f0ff]/40 shrink-0 relative overflow-hidden">
                        <div class="absolute inset-0 bg-gradient-to-br from-blue-600/20 to-[#00f0ff]/20"></div>
                        <i class="fas <?php echo htmlspecialchars($current_category['icon_class']); ?> text-4xl text-[#00f0ff] relative z-10 drop-shadow-[0_0_10px_rgba(0,240,255,0.8)]"></i>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-1">
                            <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest bg-slate-950/50 px-2 py-0.5 rounded border border-slate-700/50">
                                <?php echo htmlspecialchars($current_category['type']); ?> Matrix
                            </span>
                            <span class="text-[9px] font-black text-green-400 uppercase tracking-widest bg-green-500/10 px-2 py-0.5 rounded border border-green-500/30 flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span> Online
                            </span>
                        </div>
                        <h1 class="text-3xl md:text-4xl font-black text-white mb-2 tracking-tight"><?php echo htmlspecialchars($current_category['name']); ?></h1>
                        <p class="text-slate-400 text-sm leading-relaxed max-w-2xl"><?php echo htmlspecialchars($current_category['description']); ?></p>
                    </div>
                </div>
            </div>

            <!-- Toolbar (Sorting & Meta) -->
            <div class="bg-slate-900/60 backdrop-blur-md border border-slate-700/50 rounded-xl p-3 flex flex-col sm:flex-row justify-between items-center gap-4 z-10 relative">
                <p class="text-xs text-slate-400 font-medium">
                    Showing <span class="text-white font-bold"><?php echo count($products); ?></span> active nodes
                </p>
                
                <div class="flex items-center gap-2">
                    <label class="text-xs text-slate-500 font-bold uppercase tracking-wider">Sort:</label>
                    <select onchange="window.location.href=this.value" class="bg-slate-800 border border-slate-600 rounded-lg px-3 py-1.5 text-xs text-white focus:border-[#00f0ff] outline-none cursor-pointer appearance-none pr-8 relative">
                        <option value="index.php?module=shop&page=category&id=<?php echo $cat_id; ?>&sort=newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest Deployments</option>
                        <option value="index.php?module=shop&page=category&id=<?php echo $cat_id; ?>&sort=price_asc" <?php echo $sort == 'price_asc' ? 'selected' : ''; ?>>Price (Low to High)</option>
                        <option value="index.php?module=shop&page=category&id=<?php echo $cat_id; ?>&sort=price_desc" <?php echo $sort == 'price_desc' ? 'selected' : ''; ?>>Price (High to Low)</option>
                        <option value="index.php?module=shop&page=category&id=<?php echo $cat_id; ?>&sort=name_asc" <?php echo $sort == 'name_asc' ? 'selected' : ''; ?>>Alphabetical (A-Z)</option>
                    </select>
                </div>
            </div>

            <!-- Product Grid -->
            <?php if (empty($products)): ?>
                <div class="glass p-12 rounded-3xl text-center border border-slate-700/50 bg-slate-900/60 backdrop-blur-xl shadow-2xl relative z-10">
                    <div class="w-24 h-24 bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-6 shadow-inner border border-slate-700">
                        <i class="fas fa-ghost text-5xl text-slate-600"></i>
                    </div>
                    <h3 class="text-2xl font-black text-white mb-2 tracking-tight">Sector Empty</h3>
                    <p class="text-slate-400 max-w-sm mx-auto mb-8 text-sm leading-relaxed">No digital assets are currently active in this sector. Try checking another category.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6 relative z-10">
                    <?php foreach($products as $product): ?>
                        <!-- Reusing the polished product card component -->
                        <?php include __DIR__ . '/../home/product_card.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>