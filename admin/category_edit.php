<?php
// admin/category_edit.php

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 1. Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {
    $name = trim($_POST['name']);
    $type = $_POST['type'];
    $icon = trim($_POST['icon_class']);
    $desc = trim($_POST['description']);

    if ($name && $type) {
        try {
            $stmt = $pdo->prepare("UPDATE categories SET name = ?, type = ?, icon_class = ?, description = ? WHERE id = ?");
            $stmt->execute([$name, $type, $icon, $desc, $id]);
            redirect(admin_url('categories', ['updated' => 1]));
        } catch (Exception $e) {
            $error = "Error updating category: " . $e->getMessage();
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
    echo "<div class='p-6 bg-red-500/20 text-red-400 rounded-xl'>Category not found. <a href='".admin_url('categories')."' class='underline'>Back</a></div>";
    return;
}
?>

<div class="max-w-3xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-white">Edit Category</h1>
        <a href="<?php echo admin_url('categories'); ?>" class="text-slate-400 hover:text-white text-sm flex items-center gap-1"><i class="fas fa-arrow-left"></i> Back</a>
    </div>

    <?php if(isset($error)) echo "<div class='bg-red-500/20 text-red-400 p-4 rounded-xl border border-red-500/50 mb-6 flex items-center gap-2'><i class='fas fa-exclamation-triangle'></i> $error</div>"; ?>

    <div class="bg-slate-800 p-8 rounded-xl border border-slate-700 shadow-xl">
        <form method="POST" class="space-y-6">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1 uppercase tracking-wider">Category Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($category['name']); ?>" required class="w-full bg-slate-900 border border-slate-600 rounded-lg p-3 text-white focus:border-blue-500 outline-none transition">
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1 uppercase tracking-wider">Product Type</label>
                    <select name="type" class="w-full bg-slate-900 border border-slate-600 rounded-lg p-3 text-white focus:border-blue-500 outline-none transition">
                        <option value="subscription" <?php echo $category['type'] == 'subscription' ? 'selected' : ''; ?>>Subscription</option>
                        <option value="game" <?php echo $category['type'] == 'game' ? 'selected' : ''; ?>>Game</option>
                        <option value="gift_card" <?php echo $category['type'] == 'gift_card' ? 'selected' : ''; ?>>Gift Card</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-400 mb-1 uppercase tracking-wider">Icon Class (FontAwesome)</label>
                <div class="flex gap-3">
                    <div class="w-12 h-12 bg-slate-700 rounded-lg flex items-center justify-center text-blue-400 text-xl border border-slate-600 shadow-inner">
                        <i class="fas <?php echo htmlspecialchars($category['icon_class']); ?>"></i>
                    </div>
                    <div class="flex-1 relative">
                        <input type="text" name="icon_class" value="<?php echo htmlspecialchars($category['icon_class']); ?>" placeholder="fa-cube" class="w-full bg-slate-900 border border-slate-600 rounded-lg p-3 text-white focus:border-blue-500 outline-none transition font-mono text-sm">
                    </div>
                </div>
                <p class="text-[10px] text-slate-500 mt-1 ml-16">Example: <code>fa-gamepad</code>, <code>fa-bolt</code>, <code>fa-shield-alt</code></p>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-400 mb-1 uppercase tracking-wider">Description</label>
                <textarea name="description" rows="3" class="w-full bg-slate-900 border border-slate-600 rounded-lg p-3 text-white text-sm focus:border-blue-500 outline-none resize-none transition"><?php echo htmlspecialchars($category['description']); ?></textarea>
            </div>

            <div class="pt-6 border-t border-slate-700 flex justify-end gap-3">
                <a href="<?php echo admin_url('categories'); ?>" class="px-6 py-2.5 rounded-lg border border-slate-600 text-slate-300 hover:bg-slate-700 transition font-medium text-sm">Cancel</a>
                <button type="submit" name="update_category" class="bg-blue-600 hover:bg-blue-500 text-white px-8 py-2.5 rounded-lg font-bold shadow-lg transition text-sm flex items-center gap-2">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>

        </form>
    </div>
</div>