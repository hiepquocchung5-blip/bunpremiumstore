<?php
// admin/categories.php

// 1. Handle Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['name']);
    $type = $_POST['type'];
    $icon = trim($_POST['icon_class']);
    $desc = trim($_POST['description']);

    if ($name && $type) {
        try {
            $stmt = $pdo->prepare("INSERT INTO categories (name, type, icon_class, description) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $type, $icon, $desc]);
            redirect(admin_url('categories', ['success' => 1]));
        } catch (Exception $e) {
            $error = "Error adding category: " . $e->getMessage();
        }
    }
}

// 2. Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
        redirect(admin_url('categories', ['deleted' => 1]));
    } catch (Exception $e) {
        $error = "Cannot delete category. It contains products.";
    }
}

// 3. Fetch Categories with Product Counts
$categories = $pdo->query("
    SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count 
    FROM categories c 
    ORDER BY c.id ASC
")->fetchAll();
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-3xl font-bold text-white">Categories</h1>
        <p class="text-slate-400 text-sm mt-1">Organize your products into sections.</p>
    </div>
</div>

<?php if(isset($_GET['success'])): ?>
    <div class="bg-green-500/20 text-green-400 p-4 rounded-xl border border-green-500/50 mb-6 flex items-center gap-3">
        <i class="fas fa-check-circle"></i> Category added successfully.
    </div>
<?php endif; ?>

<?php if(isset($_GET['deleted'])): ?>
    <div class="bg-red-500/20 text-red-400 p-4 rounded-xl border border-red-500/50 mb-6 flex items-center gap-3">
        <i class="fas fa-trash-alt"></i> Category deleted.
    </div>
<?php endif; ?>

<?php if(isset($_GET['updated'])): ?>
    <div class="bg-blue-500/20 text-blue-400 p-4 rounded-xl border border-blue-500/50 mb-6 flex items-center gap-3">
        <i class="fas fa-save"></i> Category updated.
    </div>
<?php endif; ?>

<?php if(isset($error)): ?>
    <div class="bg-red-500/20 text-red-400 p-4 rounded-xl border border-red-500/50 mb-6">
        <?php echo $error; ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Add Form -->
    <div class="lg:col-span-1">
        <div class="bg-slate-800 p-6 rounded-xl border border-slate-700 shadow-lg sticky top-6">
            <h3 class="font-bold text-white mb-4 border-b border-slate-700 pb-2 flex items-center gap-2">
                <i class="fas fa-plus text-blue-500"></i> Add Category
            </h3>
            
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1 uppercase">Name</label>
                    <input type="text" name="name" placeholder="e.g. VPN Services" required class="w-full bg-slate-900 border border-slate-600 rounded-lg p-2.5 text-white text-sm focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1 uppercase">Product Type</label>
                    <select name="type" class="w-full bg-slate-900 border border-slate-600 rounded-lg p-2.5 text-white text-sm focus:border-blue-500 outline-none">
                        <option value="subscription">Subscription</option>
                        <option value="game">Game</option>
                        <option value="gift_card">Gift Card</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1 uppercase">Icon (FontAwesome)</label>
                    <div class="flex gap-2">
                        <input type="text" name="icon_class" placeholder="fa-cube" value="fa-cube" class="flex-1 bg-slate-900 border border-slate-600 rounded-lg p-2.5 text-white text-sm focus:border-blue-500 outline-none">
                        <a href="https://fontawesome.com/v5/search?m=free" target="_blank" class="bg-slate-700 hover:bg-slate-600 text-white px-3 py-2 rounded-lg flex items-center justify-center" title="Browse Icons">
                            <i class="fas fa-search"></i>
                        </a>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1 uppercase">Description</label>
                    <textarea name="description" rows="3" placeholder="Short description..." class="w-full bg-slate-900 border border-slate-600 rounded-lg p-2.5 text-white text-sm focus:border-blue-500 outline-none"></textarea>
                </div>
                <button type="submit" name="add_category" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-2.5 rounded-lg transition shadow-lg text-sm">
                    Create Category
                </button>
            </form>
        </div>
    </div>

    <!-- Category List -->
    <div class="lg:col-span-2">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach($categories as $c): ?>
                <div class="bg-slate-800 p-5 rounded-xl border border-slate-700 flex items-start gap-4 group hover:border-blue-500/30 transition shadow-lg">
                    <div class="w-12 h-12 rounded-lg bg-slate-900 flex items-center justify-center text-blue-400 text-xl border border-slate-600 group-hover:scale-110 transition shrink-0 shadow-inner">
                        <i class="fas <?php echo htmlspecialchars($c['icon_class']); ?>"></i>
                    </div>
                    
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between items-start mb-1">
                            <h4 class="font-bold text-white text-lg truncate pr-2"><?php echo htmlspecialchars($c['name']); ?></h4>
                            <div class="flex gap-2 shrink-0">
                                <a href="<?php echo admin_url('category_edit', ['id' => $c['id']]); ?>" 
                                   class="text-slate-500 hover:text-blue-400 transition bg-slate-900 p-1.5 rounded-md hover:bg-blue-900/20" 
                                   title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="<?php echo admin_url('categories', ['delete' => $c['id']]); ?>" 
                                   class="text-slate-500 hover:text-red-400 transition bg-slate-900 p-1.5 rounded-md hover:bg-red-900/20" 
                                   onclick="return confirm('Delete this category?')"
                                   title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-[10px] uppercase font-bold bg-slate-700 text-slate-300 px-2 py-0.5 rounded border border-slate-600"><?php echo htmlspecialchars($c['type']); ?></span>
                            <span class="text-[10px] text-slate-500"><?php echo $c['product_count']; ?> Products</span>
                        </div>
                        <p class="text-slate-400 text-xs line-clamp-2"><?php echo htmlspecialchars($c['description']); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>