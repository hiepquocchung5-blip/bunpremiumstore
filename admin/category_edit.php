<?php
// admin/category_edit.php

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 1. Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {
    $name = trim($_POST['name']);
    $type = $_POST['type'];
    $desc = trim($_POST['description']);

    if ($name && $type) {
        
        $img_sql = "";
        $params = [$name, $type, $desc];

        // Handle Image Replacement
        if (!empty($_FILES['image']['name'])) {
            // Fetch old image to delete
            $stmt = $pdo->prepare("SELECT image_url FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            $old_img = $stmt->fetchColumn();

            // Upload new
            $target_dir = "../uploads/categories/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);

            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $filename = "cat_" . uniqid() . "." . $ext;
            $target_file = $target_dir . $filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $img_sql = ", image_url = ?";
                $params[] = "uploads/categories/" . $filename;
                
                // Delete old file
                if ($old_img && file_exists("../" . $old_img)) {
                    unlink("../" . $old_img);
                }
            } else {
                $error = "Failed to move uploaded file. Check folder permissions.";
            }
        }

        if (!isset($error)) {
            $params[] = $id;
            try {
                $stmt = $pdo->prepare("UPDATE categories SET name = ?, type = ?, description = ? $img_sql WHERE id = ?");
                $stmt->execute($params);
                redirect(admin_url('categories', ['updated' => 1]));
            } catch (Exception $e) {
                $error = "Error updating category: " . $e->getMessage();
            }
        }

    } else {
        $error = "Name and Type are required.";
    }
}

// 2. Fetch Category
$stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->execute([$id]);
$category = $stmt->fetch();

if (!$category) {
    echo "<div class='p-6 bg-red-500/20 text-red-400 rounded-xl'>Category not found. <a href='".admin_url('categories')."' class='underline hover:text-white'>Back</a></div>";
    return;
}
?>

<div class="max-w-3xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-white">Edit Category</h1>
        <a href="<?php echo admin_url('categories'); ?>" class="text-slate-400 hover:text-white text-sm flex items-center gap-1"><i class="fas fa-arrow-left"></i> Back</a>
    </div>

    <?php if(isset($error)) echo "<div class='bg-red-500/20 text-red-400 p-4 rounded-xl border border-red-500/50 mb-6 flex items-center gap-2 animate-pulse'><i class='fas fa-exclamation-triangle'></i> $error</div>"; ?>

    <div class="bg-slate-800 p-8 rounded-2xl border border-slate-700 shadow-xl relative overflow-hidden group hover:border-[#00f0ff]/30 transition-all duration-300">
        
        <div class="absolute -right-10 -top-10 w-40 h-40 bg-[#00f0ff]/5 rounded-full blur-3xl pointer-events-none group-hover:bg-[#00f0ff]/10 transition-colors duration-500"></div>

        <form method="POST" enctype="multipart/form-data" class="space-y-6 relative z-10">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 mb-1 uppercase tracking-widest">Category Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($category['name']); ?>" required class="w-full bg-slate-900 border border-slate-600 rounded-xl p-3 text-white focus:border-[#00f0ff] outline-none shadow-inner transition-all">
                </div>
                
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 mb-1 uppercase tracking-widest">Product Type</label>
                    <div class="relative">
                        <select name="type" class="w-full bg-slate-900 border border-slate-600 rounded-xl p-3 text-white focus:border-[#00f0ff] outline-none shadow-inner transition-all appearance-none cursor-pointer">
                            <option value="subscription" <?php echo $category['type'] == 'subscription' ? 'selected' : ''; ?>>Subscription</option>
                            <option value="game" <?php echo $category['type'] == 'game' ? 'selected' : ''; ?>>Game</option>
                            <option value="gift_card" <?php echo $category['type'] == 'gift_card' ? 'selected' : ''; ?>>Gift Card</option>
                        </select>
                        <i class="fas fa-chevron-down absolute right-4 top-4 text-slate-500 pointer-events-none text-xs"></i>
                    </div>
                </div>
            </div>

            <!-- Image Handling -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
                
                <!-- Current Image Preview -->
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 mb-1 uppercase tracking-widest">Current Image</label>
                    <div class="w-full h-32 rounded-xl overflow-hidden bg-slate-900 border border-slate-600 flex items-center justify-center relative shadow-inner">
                        <?php if(!empty($category['image_url'])): ?>
                            <img src="<?php echo MAIN_SITE_URL . $category['image_url']; ?>" alt="Category Image" class="w-full h-full object-cover">
                            <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 hover:opacity-100 transition-opacity duration-300">
                                <span class="text-xs font-bold text-white uppercase tracking-wider bg-black/60 px-3 py-1 rounded-lg backdrop-blur">Active</span>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-slate-600">
                                <i class="fas fa-image text-3xl mb-2"></i>
                                <p class="text-xs">No image assigned</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Upload New Image -->
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 mb-1 uppercase tracking-widest">Replace Image (Optional)</label>
                    <div class="relative border-2 border-dashed border-slate-600 rounded-xl h-32 text-center hover:bg-slate-900/50 hover:border-[#00f0ff]/50 transition-all cursor-pointer group/upload flex flex-col items-center justify-center bg-slate-900/30">
                        <input type="file" name="image" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="document.getElementById('edit-cat-img-label').innerHTML = `<span class='text-green-400 font-bold'><i class='fas fa-check'></i> ${this.files[0].name}</span>`">
                        <i class="fas fa-cloud-upload-alt text-2xl text-slate-500 mb-2 group-hover/upload:text-[#00f0ff] transition-colors group-hover/upload:-translate-y-1 transform"></i>
                        <p id="edit-cat-img-label" class="text-xs text-slate-400 group-hover/upload:text-white transition-colors px-2 truncate w-full">Click or drag new JPG/PNG</p>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-slate-400 mb-1 uppercase tracking-widest">Description</label>
                <textarea name="description" rows="3" placeholder="Brief sector overview..." class="w-full bg-slate-900 border border-slate-600 rounded-xl p-3 text-white text-sm focus:border-[#00f0ff] outline-none resize-none shadow-inner transition-all"><?php echo htmlspecialchars($category['description']); ?></textarea>
            </div>

            <div class="pt-6 border-t border-slate-700/50 flex justify-end gap-3 mt-4">
                <a href="<?php echo admin_url('categories'); ?>" class="px-6 py-3 rounded-xl border border-slate-600 text-slate-300 hover:bg-slate-700 hover:text-white transition font-bold text-sm tracking-wide uppercase">Cancel</a>
                <button type="submit" name="update_category" class="bg-gradient-to-r from-blue-600 to-[#00f0ff] hover:from-blue-500 hover:to-[#00f0ff] text-slate-900 px-8 py-3 rounded-xl font-black shadow-[0_0_15px_rgba(0,240,255,0.3)] transition transform active:scale-95 text-sm uppercase tracking-widest flex items-center gap-2">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>

        </form>
    </div>
</div>