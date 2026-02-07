<?php
// admin/banners.php

// 1. Handle Add Banner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_banner'])) {
    $title = trim($_POST['title']);
    $url = trim($_POST['target_url']);
    
    // Image Upload
    if (!empty($_FILES['image']['name'])) {
        $target_dir = "../uploads/banners/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

        $filename = uniqid('banner_') . "_" . basename($_FILES['image']['name']);
        $target_file = $target_dir . $filename;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            // Save relative path to DB (remove ../ for frontend access)
            $db_path = "uploads/banners/" . $filename;
            
            $stmt = $pdo->prepare("INSERT INTO banners (title, image_path, target_url) VALUES (?, ?, ?)");
            $stmt->execute([$title, $db_path, $url]);
            
            // Redirect using helper
            redirect(admin_url('banners', ['success' => 1]));
        } else {
            $error = "Failed to upload image.";
        }
    } else {
        $error = "Please select an image.";
    }
}

// 2. Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Get path to delete file
    $stmt = $pdo->prepare("SELECT image_path FROM banners WHERE id = ?");
    $stmt->execute([$id]);
    $img = $stmt->fetchColumn();
    
    if ($img && file_exists("../" . $img)) {
        unlink("../" . $img);
    }

    $pdo->prepare("DELETE FROM banners WHERE id = ?")->execute([$id]);
    redirect(admin_url('banners', ['deleted' => 1]));
}

// 3. Fetch Banners
$banners = $pdo->query("SELECT * FROM banners ORDER BY id DESC")->fetchAll();
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-3xl font-bold text-white">Banner Management</h1>
        <p class="text-slate-400 text-sm mt-1">Manage homepage carousel slides.</p>
    </div>
</div>

<?php if(isset($_GET['success'])): ?>
    <div class="bg-green-500/20 border border-green-500/50 text-green-400 p-4 rounded-xl mb-6 flex items-center gap-2">
        <i class="fas fa-check-circle"></i> Banner added successfully.
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Add Banner Form -->
    <div class="lg:col-span-1">
        <div class="bg-slate-800 p-6 rounded-xl border border-slate-700 shadow-lg sticky top-6">
            <h3 class="font-bold text-white mb-4 border-b border-slate-700 pb-2">Add New Slide</h3>
            
            <?php if(isset($error)) echo "<div class='text-red-400 text-sm mb-3'>$error</div>"; ?>

            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Banner Title</label>
                    <input type="text" name="title" placeholder="e.g. Special Offer" required class="w-full bg-slate-900 border border-slate-600 rounded p-2.5 text-white focus:border-blue-500 outline-none text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Target URL (Optional)</label>
                    <input type="text" name="target_url" placeholder="https://t.me/..." class="w-full bg-slate-900 border border-slate-600 rounded p-2.5 text-white focus:border-blue-500 outline-none text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Image (Landscape)</label>
                    <input type="file" name="image" accept="image/*" required class="w-full bg-slate-900 border border-slate-600 rounded p-2 text-slate-400 text-sm file:mr-4 file:py-1 file:px-3 file:rounded-full file:border-0 file:text-xs file:bg-slate-700 file:text-slate-300 hover:file:bg-slate-600">
                </div>
                <button type="submit" name="add_banner" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-2.5 rounded-lg transition shadow-lg text-sm">
                    <i class="fas fa-plus mr-1"></i> Upload Banner
                </button>
            </form>
        </div>
    </div>

    <!-- Banner List -->
    <div class="lg:col-span-2 space-y-4">
        <?php if(empty($banners)): ?>
            <div class="text-center p-10 border-2 border-dashed border-slate-700 rounded-xl text-slate-500">
                <i class="far fa-images text-4xl mb-2"></i>
                <p>No banners active.</p>
            </div>
        <?php else: ?>
            <?php foreach($banners as $b): ?>
                <div class="bg-slate-800 p-4 rounded-xl border border-slate-700 flex flex-col sm:flex-row items-start sm:items-center gap-4 group hover:border-slate-600 transition">
                    <!-- Image Preview -->
                    <div class="w-full sm:w-40 h-24 rounded-lg overflow-hidden bg-black shrink-0 relative">
                        <img src="../<?php echo $b['image_path']; ?>" class="w-full h-full object-cover opacity-80 group-hover:opacity-100 transition">
                    </div>
                    
                    <!-- Info -->
                    <div class="flex-1 min-w-0">
                        <h4 class="font-bold text-white text-lg truncate"><?php echo htmlspecialchars($b['title']); ?></h4>
                        <?php if($b['target_url']): ?>
                            <a href="<?php echo htmlspecialchars($b['target_url']); ?>" target="_blank" class="text-blue-400 text-xs hover:underline truncate block">
                                <i class="fas fa-link mr-1"></i> <?php echo htmlspecialchars($b['target_url']); ?>
                            </a>
                        <?php else: ?>
                            <span class="text-slate-500 text-xs">No link attached</span>
                        <?php endif; ?>
                        <p class="text-slate-600 text-[10px] mt-2">Added: <?php echo date('M d, Y', strtotime($b['created_at'])); ?></p>
                    </div>

                    <!-- Actions -->
                    <a href="<?php echo admin_url('banners', ['delete' => $b['id']]); ?>" 
                       class="text-slate-500 hover:text-red-400 p-2 transition bg-slate-900 hover:bg-red-900/20 rounded-lg"
                       onclick="return confirm('Delete this banner?')">
                        <i class="fas fa-trash-alt"></i>
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>