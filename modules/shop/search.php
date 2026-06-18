<?php
// modules/shop/search.php
// PRODUCTION v2.0 - Auto-Redirect, Neon UI & Image URL Fix

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

// 1. FEATURE: Redirect empty search to the Global Store Hub
if (empty($query)) {
    redirect('index.php?module=shop&page=category');
}

// 2. Perform Search (Includes Name, Description, and Category Name)
$search_term = "%$query%";
$stmt = $pdo->prepare("
    SELECT p.*, c.name as cat_name, c.image_url as cat_image 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    WHERE p.name LIKE ? OR c.name LIKE ? OR p.description LIKE ?
    ORDER BY p.id DESC
");
$stmt->execute([$search_term, $search_term, $search_term]);
$results = $stmt->fetchAll();

// 3. Get Agent Discount
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

<!-- Background Effects -->
<div class="fixed inset-0 w-full h-full dm-gradient-bg -z-20"></div>
<div class="fixed top-0 left-0 w-full h-full -z-10 opacity-20 pointer-events-none">
    <div class="absolute top-[-10%] left-[-10%] w-[50%] h-[50%] bg-blue-600/20 rounded-full blur-[120px]"></div>
</div>

<div class="max-w-7xl mx-auto px-6 pb-20 relative z-10">

    <!-- Search Header -->
    <div class="mb-12 text-center pt-12 md:pt-16">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-slate-800 rounded-[1.5rem] border border-white/5 shadow-xl mb-6 text-blue-400 text-2xl">
            <i class="fas fa-search"></i>
        </div>
        
        <h1 class="text-3xl md:text-5xl font-bold text-white mb-4 tracking-tight">Search Results</h1>
        <p class="text-slate-500 font-medium">
            Found <span class="text-white font-bold mx-1"><?php echo count($results); ?></span> items for <span class="text-blue-400 font-bold italic">"<?php echo htmlspecialchars($query); ?>"</span>
        </p>
    </div>

    <!-- Refine Search Bar -->
    <div class="max-w-2xl mx-auto mb-16">
        <form action="index.php" method="GET" class="relative group">
            <input type="hidden" name="module" value="shop">
            <input type="hidden" name="page" value="search">
            
            <input type="text" name="q" value="<?php echo htmlspecialchars($query); ?>" 
                   class="w-full bg-slate-800/40 border border-white/5 focus:border-blue-500/50 rounded-2xl py-5 pl-6 pr-32 text-white placeholder-slate-600 outline-none shadow-2xl backdrop-blur-xl transition-all">
            
            <button type="submit" class="absolute right-2 top-2 bottom-2 dm-btn-primary px-8 transition-all active:scale-95 text-xs uppercase tracking-widest">
                Search
            </button>
        </form>
    </div>

    <!-- Results Grid -->
    <?php if (empty($results)): ?>
        <div class="bg-slate-800/20 border border-white/5 p-12 md:p-20 rounded-[2.5rem] text-center max-w-2xl mx-auto space-y-8 shadow-2xl">
            <div class="w-20 h-20 bg-slate-900 rounded-full flex items-center justify-center mx-auto text-slate-700 text-3xl">
                <i class="fas fa-search-minus"></i>
            </div>
            <div class="space-y-3">
                <h3 class="text-2xl font-bold text-white">No items found</h3>
                <p class="text-slate-500 max-w-sm mx-auto text-sm leading-relaxed">We couldn't find any products matching your search. Try different keywords or browse our categories.</p>
            </div>
            <a href="index.php?module=shop&page=category" class="inline-flex items-center gap-3 dm-btn-primary px-8 py-4 transition-all hover:scale-105 active:scale-95 shadow-xl">
                Browse Categories
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
            <?php foreach($results as $product): ?>
                <?php include __DIR__ . '/../home/product_card.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

</div>
