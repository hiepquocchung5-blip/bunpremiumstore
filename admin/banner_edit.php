<?php
// admin/banner_edit.php

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 1. Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_banner'])) {
    $title = trim($_POST['title']);
    $url = trim($_POST['target_url']);
    $order = (int)$_POST['display_order'];
    
    // Handle Image Replacement
    $img_sql = "";
    $params = [$title, $url, $order];

    if (!empty($_FILES['image']['name'])) {
        // Fetch old image to delete
        $stmt = $pdo->prepare("SELECT image_path FROM banners WHERE id = ?");
        $stmt->execute([$id]);
        $old_img = $stmt->fetchColumn();

        // Upload new
        $target_dir = "../uploads/banners/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);

        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $filename = "banner_" . uniqid() . "_" . date('Ymd') . "." . $ext;
        $target_file = $target_dir . $filename;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            $img_sql = ", image_path = ?";
            // Store path relative to main site root
            $params[] = "uploads/banners/" . $filename;
            
            // Delete old file
            if ($old_img && file_exists("../" . $old_img)) {
                unlink("../" . $old_img);
            }
        }
    }

    $params[] = $id;

    $stmt = $pdo->prepare("UPDATE banners SET title = ?, target_url = ?, display_order = ? $img_sql WHERE id = ?");
    $stmt->execute($params);
    
    redirect(admin_url('banners', ['updated' => 1]));
}

// 2. Fetch Banner Data
$stmt = $pdo->prepare("SELECT * FROM banners WHERE id = ?");
$stmt->execute([$id]);
$banner = $stmt->fetch();

if (!$banner) {
    echo "<div class='p-6 bg-red-500/20 text-red-400 rounded-xl border border-red-500/50'>Banner not found. <a href='".admin_url('banners')."' class='underline hover:text-white'>Back</a></div>";
    return;
}
?>

<div class="max-w-2xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-white">Edit Banner</h1>
        <a href="<?php echo admin_url('banners'); ?>" class="text-slate-400 hover:text-white text-sm flex items-center gap-1"><i class="fas fa-arrow-left"></i> Back</a>
    </div>

    <div class="bg-slate-800 p-8 rounded-xl border border-slate-700 shadow-xl">
        <form method="POST" enctype="multipart/form-data" class="space-y-6">
            
            <!-- Current Image Preview -->
            <div class="mb-6">
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Current Slide</label>
                <div class="rounded-lg overflow-hidden border border-slate-600 bg-black relative group">
                    <img src="<?php echo MAIN_SITE_URL . $banner['image_path']; ?>" class="w-full h-48 object-cover opacity-90 group-hover:opacity-100 transition duration-500">
                    <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent p-4">
                        <p class="text-white font-bold truncate"><?php echo htmlspecialchars($banner['title']); ?></p>
                    </div>
                </div>
            </div>

            <!-- Upload New Image -->
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Replace Image (Optional)</label>
                <div class="relative border-2 border-dashed border-slate-600 rounded-lg p-4 text-center hover:bg-slate-700/50 transition cursor-pointer">
                    <input type="file" name="image" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" onchange="document.getElementById('edit-file-label').innerText = this.files[0].name">
                    <i class="fas fa-camera text-2xl text-slate-500 mb-1"></i>
                    <p id="edit-file-label" class="text-xs text-slate-400">Click to select new file</p>
                </div>
            </div>

            <!-- Fields -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Banner Title</label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($banner['title']); ?>" required class="w-full bg-slate-900 border border-slate-600 rounded-lg p-3 text-white focus:border-blue-500 outline-none transition">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Display Order</label>
                    <input type="number" name="display_order" value="<?php echo $banner['display_order']; ?>" class="w-full bg-slate-900 border border-slate-600 rounded-lg p-3 text-white focus:border-blue-500 outline-none transition">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Target URL</label>
                <div class="relative">
                    <i class="fas fa-link absolute left-3 top-3.5 text-slate-500 text-sm"></i>
                    <input type="text" name="target_url" value="<?php echo htmlspecialchars($banner['target_url']); ?>" placeholder="https://..." class="w-full bg-slate-900 border border-slate-600 rounded-lg p-3 pl-9 text-white focus:border-blue-500 outline-none transition">
                </div>
            </div>

            <div class="pt-6 border-t border-slate-700 flex gap-3">
                <a href="<?php echo admin_url('banners'); ?>" class="flex-1 bg-slate-700 hover:bg-slate-600 text-white font-bold py-3 rounded-lg text-center transition">Cancel</a>
                <button type="submit" name="update_banner" class="flex-1 bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 rounded-lg shadow-lg transition flex justify-center items-center gap-2">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>