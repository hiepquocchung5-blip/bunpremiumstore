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

<!-- Animated Cyberpunk Background -->
<div class="fixed inset-0 w-full h-full bg-slate-950 -z-20 pointer-events-none"></div>
<div class="fixed top-0 -left-4 w-96 h-96 bg-[#00f0ff] rounded-full mix-blend-multiply filter blur-[128px] opacity-10 animate-blob -z-10 pointer-events-none"></div>
<div class="fixed top-40 -right-4 w-96 h-96 bg-purple-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-10 animate-blob animation-delay-2000 -z-10 pointer-events-none"></div>

<div class="max-w-7xl mx-auto px-4 pb-12 relative z-10">

    <!-- Search Header -->
    <div class="mb-10 text-center relative pt-8 md:pt-12">
        <div class="inline-block mb-4 relative group">
            <div class="absolute inset-0 bg-[#00f0ff]/20 blur-xl rounded-full group-hover:bg-[#00f0ff]/40 transition duration-500"></div>
            <div class="w-16 h-16 bg-slate-900 rounded-2xl flex items-center justify-center border border-[#00f0ff]/50 shadow-[0_0_20px_rgba(0,240,255,0.2)] relative z-10">
                <i class="fas fa-search text-3xl text-[#00f0ff]"></i>
            </div>
        </div>
        
        <h2 class="text-3xl md:text-5xl font-black text-white mb-4 tracking-tight">Database Query</h2>
        <p class="text-slate-400 font-medium text-sm md:text-base">
            Retrieved <span class="text-[#00f0ff] font-bold mx-1 bg-[#00f0ff]/10 px-2 py-0.5 rounded border border-[#00f0ff]/20"><?php echo count($results); ?></span> records matching <span class="text-white font-bold font-mono">"<?php echo htmlspecialchars($query); ?>"</span>
        </p>
    </div>

    <!-- Refine Search Bar -->
    <div class="max-w-2xl mx-auto mb-12">
        <form action="index.php" method="GET" class="relative group">
            <input type="hidden" name="module" value="shop">
            <input type="hidden" name="page" value="search">
            
            <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none">
                <i class="fas fa-terminal text-[#00f0ff]/50 group-focus-within:text-[#00f0ff] transition-colors"></i>
            </div>
            
            <input type="text" name="q" value="<?php echo htmlspecialchars($query); ?>" 
                   class="w-full bg-slate-900/80 border border-slate-700 focus:border-[#00f0ff] rounded-2xl py-4 pl-12 pr-32 text-white placeholder-slate-500 outline-none shadow-inner backdrop-blur-sm transition-all focus:shadow-[0_0_20px_rgba(0,240,255,0.15)] font-mono">
            
            <button type="submit" class="absolute right-2 top-2 bottom-2 bg-gradient-to-r from-blue-600 to-[#00f0ff] hover:from-blue-500 hover:to-[#00f0ff] text-slate-900 font-black px-6 rounded-xl transition-all shadow-[0_0_15px_rgba(0,240,255,0.2)] transform active:scale-95 text-xs uppercase tracking-widest flex items-center gap-2">
                <span class="hidden sm:inline">Execute</span> <i class="fas fa-arrow-right"></i>
            </button>
        </form>
    </div>

    <!-- Results Grid -->
    <?php if (empty($results)): ?>
        <div class="glass p-10 md:p-14 rounded-3xl text-center border border-slate-700/50 bg-slate-900/60 backdrop-blur-xl shadow-2xl max-w-2xl mx-auto">
            <div class="w-24 h-24 bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-6 shadow-inner border border-slate-700">
                <i class="fas fa-ghost text-5xl text-slate-600"></i>
            </div>
            <h3 class="text-2xl font-black text-white mb-3 tracking-tight">Null Reference</h3>
            <p class="text-slate-400 max-w-sm mx-auto mb-8 text-sm leading-relaxed">No digital assets match your query parameter. Adjust your search logic or browse the master directory.</p>
            <a href="index.php?module=shop&page=category" class="bg-slate-800 hover:bg-slate-700 border border-slate-600 text-white px-8 py-3.5 rounded-xl font-bold transition shadow-lg inline-flex items-center gap-2 uppercase tracking-widest text-sm">
                <i class="fas fa-network-wired text-[#00f0ff]"></i> Access Directory
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 xl:gap-8">
            <?php foreach($results as $product): ?>
                <!-- Reusing the master product card component -->
                <?php include __DIR__ . '/../home/product_card.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>