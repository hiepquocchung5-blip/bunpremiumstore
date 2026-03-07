<?php
// admin/categories.php

// 1. Handle Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['name']);
    $type = $_POST['type'];
    $desc = trim($_POST['description']);
    $image_url = null;

    if ($name && $type) {
        
        // Image Upload Logic
        if (!empty($_FILES['image']['name'])) {
            $target_dir = "../uploads/categories/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);

            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            if (in_array($ext, $allowed_ext)) {
                $filename = "cat_" . uniqid() . '.' . $ext;
                $target_file = $target_dir . $filename;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                    $image_url = "uploads/categories/" . $filename;
                } else {
                    $error = "Failed to upload image. Check directory permissions.";
                }
            } else {
                $error = "Invalid image format. Use JPG, PNG, WEBP, or GIF.";
            }
        }

        if (!isset($error)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO categories (name, type, image_url, description) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $type, $image_url, $desc]);
                redirect(admin_url('categories', ['success' => 1]));
            } catch (Exception $e) {
                $error = "Error adding category: " . $e->getMessage();
            }
        }
    } else {
        $error = "Name and Type are required fields.";
    }
}

// 2. Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        // Fetch image path to delete file from server
        $stmt = $pdo->prepare("SELECT image_url FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $img_path = $stmt->fetchColumn();

        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
        
        // Delete physical file if exists
        if ($img_path && file_exists("../" . $img_path)) {
            unlink("../" . $img_path);
        }

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
        <p class="text-slate-400 text-sm mt-1">Organize your products into sections with custom imagery.</p>
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
    <div class="bg-red-500/20 text-red-400 p-4 rounded-xl border border-red-500/50 mb-6 flex items-center gap-2">
        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Add Form -->
    <div class="lg:col-span-1">
        <div class="bg-slate-800 p-6 rounded-xl border border-slate-700 shadow-lg sticky top-6">
            <h3 class="font-bold text-white mb-4 border-b border-slate-700 pb-2 flex items-center gap-2">
                <i class="fas fa-plus text-blue-500"></i> Add Category
            </h3>
            
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1 uppercase tracking-wider">Name</label>
                    <input type="text" name="name" placeholder="e.g. PC Games" required class="w-full bg-slate-900 border border-slate-600 rounded-lg p-2.5 text-white text-sm focus:border-blue-500 outline-none transition">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1 uppercase tracking-wider">Product Type</label>
                    <select name="type" class="w-full bg-slate-900 border border-slate-600 rounded-lg p-2.5 text-white text-sm focus:border-blue-500 outline-none transition">
                        <option value="subscription">Subscription</option>
                        <option value="game">Game</option>
                        <option value="gift_card">Gift Card</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1 uppercase tracking-wider">Category Image</label>
                    <div class="relative border-2 border-dashed border-slate-600 rounded-lg p-4 text-center hover:bg-slate-700/50 transition cursor-pointer group">
                        <input type="file" name="image" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="document.getElementById('cat-img-label').innerHTML = `<span class='text-green-400'><i class='fas fa-check'></i> ${this.files[0].name}</span>`">
                        <i class="fas fa-image text-2xl text-slate-500 mb-2 group-hover:text-blue-400 transition"></i>
                        <p id="cat-img-label" class="text-xs text-slate-400 transition">Click to upload JPG/PNG</p>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1 uppercase tracking-wider">Description</label>
                    <textarea name="description" rows="3" placeholder="Short description..." class="w-full bg-slate-900 border border-slate-600 rounded-lg p-2.5 text-white text-sm focus:border-blue-500 outline-none resize-none transition"></textarea>
                </div>
                <button type="submit" name="add_category" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 rounded-lg transition shadow-lg text-sm flex justify-center items-center gap-2">
                    <i class="fas fa-save"></i> Create Category
                </button>
            </form>
        </div>
    </div>

    <!-- Category List -->
    <div class="lg:col-span-2">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach($categories as $c): ?>
                <div class="bg-slate-800 p-5 rounded-xl border border-slate-700 flex items-start gap-4 group hover:border-blue-500/30 transition shadow-lg">
                    
                    <!-- Dynamic Image/Icon Display -->
                    <div class="w-14 h-14 rounded-lg bg-slate-900 flex items-center justify-center text-blue-400 text-2xl border border-slate-600 group-hover:border-blue-500/50 transition shrink-0 shadow-inner overflow-hidden relative">
                        <?php if(!empty($c['image_url'])): ?>
                            <img src="<?php echo MAIN_SITE_URL . $c['image_url']; ?>" alt="<?php echo htmlspecialchars($c['name']); ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-300">
                        <?php else: ?>
                            <i class="fas fa-folder"></i>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between items-start mb-1">
                            <h4 class="font-bold text-white text-lg truncate pr-2 group-hover:text-blue-400 transition"><?php echo htmlspecialchars($c['name']); ?></h4>
                            <div class="flex gap-2 shrink-0">
                                <a href="<?php echo admin_url('category_edit', ['id' => $c['id']]); ?>" 
                                   class="text-slate-500 hover:text-blue-400 transition bg-slate-900 p-1.5 rounded-md hover:bg-blue-900/20 border border-slate-700 hover:border-blue-500/30" 
                                   title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="<?php echo admin_url('categories', ['delete' => $c['id']]); ?>" 
                                   class="text-slate-500 hover:text-red-400 transition bg-slate-900 p-1.5 rounded-md hover:bg-red-900/20 border border-slate-700 hover:border-red-500/30" 
                                   onclick="return confirm('WARNING: Are you sure you want to delete this category? Ensure no products are attached.')"
                                   title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-[10px] uppercase font-bold bg-slate-700 text-slate-300 px-2 py-0.5 rounded border border-slate-600"><?php echo htmlspecialchars($c['type']); ?></span>
                            <span class="text-[10px] text-slate-400 bg-slate-900 px-2 py-0.5 rounded-full border border-slate-700"><?php echo $c['product_count']; ?> Products</span>
                        </div>
                        <p class="text-slate-400 text-xs line-clamp-2"><?php echo htmlspecialchars($c['description']); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if(empty($categories)): ?>
                <div class="col-span-1 md:col-span-2 text-center p-12 bg-slate-800/50 rounded-2xl border-2 border-dashed border-slate-700 text-slate-500">
                    <i class="fas fa-folder-open text-4xl mb-3 opacity-50"></i>
                    <p>No categories defined yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>