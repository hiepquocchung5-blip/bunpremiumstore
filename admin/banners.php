<?php
// admin/banners.php

// 1. Handle Add Banner (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_banner'])) {
    $title = trim($_POST['title']);
    $url = trim($_POST['target_url']);
    $display_order = (int)($_POST['display_order'] ?? 0);
    
    // Image Upload Logic
    if (!empty($_FILES['image']['name'])) {
        // Physical path: Go up one level from admin/ to root, then into uploads/
        $target_dir = "../uploads/banners/";
        
        // Ensure directory exists
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }

        $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        if (in_array($file_ext, $allowed_ext)) {
            // Generate unique name to prevent overwriting
            $filename = "banner_" . uniqid() . "_" . date('Ymd') . "." . $file_ext;
            $target_file = $target_dir . $filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                // DB Path: Relative to the main website root (e.g., "uploads/banners/image.jpg")
                $db_path = "uploads/banners/" . $filename;
                
                $stmt = $pdo->prepare("INSERT INTO banners (title, image_path, target_url, display_order) VALUES (?, ?, ?, ?)");
                $stmt->execute([$title, $db_path, $url, $display_order]);
                
                redirect(admin_url('banners', ['success' => 1]));
            } else {
                $error = "Failed to move uploaded file. Check folder permissions (755).";
            }
        } else {
            $error = "Invalid file type. Only JPG, PNG, and WEBP allowed.";
        }
    } else {
        $error = "Please select an image.";
    }
}

// 2. Handle Delete Banner (GET)
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Fetch image path to delete file
    $stmt = $pdo->prepare("SELECT image_path FROM banners WHERE id = ?");
    $stmt->execute([$id]);
    $img_path = $stmt->fetchColumn();
    
    // Delete physical file if exists
    if ($img_path) {
        $physical_path = "../" . $img_path; // Reconstruct physical path
        if (file_exists($physical_path)) {
            unlink($physical_path);
        }
    }

    // Delete DB Record
    $pdo->prepare("DELETE FROM banners WHERE id = ?")->execute([$id]);
    redirect(admin_url('banners', ['deleted' => 1]));
}

// 3. Fetch Banners
$banners = $pdo->query("SELECT * FROM banners ORDER BY display_order ASC, id DESC")->fetchAll();
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-3xl font-bold text-white">Banner Management</h1>
        <p class="text-slate-400 text-sm mt-1">Manage homepage carousel slides.</p>
    </div>
</div>

<!-- Success/Error Messages -->
<?php if(isset($_GET['success'])): ?>
    <div class="bg-green-500/20 border border-green-500/50 text-green-400 p-4 rounded-xl mb-6 flex items-center gap-3 animate-pulse">
        <i class="fas fa-check-circle"></i> Banner added successfully.
    </div>
<?php endif; ?>

<?php if(isset($_GET['deleted'])): ?>
    <div class="bg-red-500/20 border border-red-500/50 text-red-400 p-4 rounded-xl mb-6 flex items-center gap-3">
        <i class="fas fa-trash-alt"></i> Banner deleted.
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Add Banner Form -->
    <div class="lg:col-span-1">
        <div class="bg-slate-800 p-6 rounded-xl border border-slate-700 shadow-lg sticky top-6">
            <h3 class="font-bold text-white mb-4 border-b border-slate-700 pb-2 flex items-center gap-2">
                <i class="fas fa-plus-circle text-blue-500"></i> Add New Slide
            </h3>
            
            <?php if(isset($error)) echo "<div class='text-red-400 text-sm mb-3 bg-red-900/20 p-2 rounded border border-red-900/50'>$error</div>"; ?>

            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Banner Title</label>
                    <input type="text" name="title" placeholder="e.g. Special Offer" required 
                           class="w-full bg-slate-900 border border-slate-600 rounded-lg p-2.5 text-white focus:border-blue-500 outline-none text-sm placeholder-slate-600">
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Target URL (Optional)</label>
                    <input type="text" name="target_url" placeholder="https://..." 
                           class="w-full bg-slate-900 border border-slate-600 rounded-lg p-2.5 text-white focus:border-blue-500 outline-none text-sm placeholder-slate-600">
                    <p class="text-[10px] text-slate-500 mt-1">Where user goes when clicking the banner.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Order (Optional)</label>
                    <input type="number" name="display_order" value="0" 
                           class="w-full bg-slate-900 border border-slate-600 rounded-lg p-2.5 text-white focus:border-blue-500 outline-none text-sm">
                    <p class="text-[10px] text-slate-500 mt-1">Lower numbers appear first.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Image (Landscape)</label>
                    <div class="relative border-2 border-dashed border-slate-600 rounded-lg p-6 text-center hover:bg-slate-700/50 transition group cursor-pointer">
                        <input type="file" name="image" accept="image/*" required 
                               class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                               onchange="document.getElementById('file-label').innerText = this.files[0].name">
                        <i class="fas fa-cloud-upload-alt text-3xl text-slate-500 mb-2 group-hover:text-blue-400 transition"></i>
                        <p id="file-label" class="text-xs text-slate-400 group-hover:text-white transition">Click to Upload JPG/PNG</p>
                    </div>
                </div>

                <button type="submit" name="add_banner" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-2.5 rounded-lg transition shadow-lg text-sm flex justify-center items-center gap-2">
                    <i class="fas fa-upload"></i> Upload Banner
                </button>
            </form>
        </div>
    </div>

    <!-- Banner List -->
    <div class="lg:col-span-2 space-y-4">
        <?php if(empty($banners)): ?>
            <div class="text-center p-12 border-2 border-dashed border-slate-700 rounded-xl text-slate-500 bg-slate-800/50">
                <i class="far fa-images text-5xl mb-3 opacity-30"></i>
                <p class="font-medium">No banners active.</p>
                <p class="text-xs mt-1">Upload a banner to feature products on the homepage.</p>
            </div>
        <?php else: ?>
            <?php foreach($banners as $b): ?>
                <div class="bg-slate-800 p-4 rounded-xl border border-slate-700 flex flex-col sm:flex-row items-start sm:items-center gap-5 group hover:border-slate-600 transition shadow-md">
                    
                    <!-- Image Preview -->
                    <!-- Uses MAIN_SITE_URL constant to load image across subdomains -->
                    <div class="w-full sm:w-48 h-28 rounded-lg overflow-hidden bg-black shrink-0 relative border border-slate-600 shadow-sm">
                        <img src="<?php echo MAIN_SITE_URL . $b['image_path']; ?>" 
                             class="w-full h-full object-cover opacity-90 group-hover:opacity-100 transition duration-300"
                             alt="Banner Preview">
                        <div class="absolute bottom-0 left-0 bg-black/60 px-2 py-0.5 text-[10px] text-white rounded-tr">
                            Order: <?php echo $b['display_order']; ?>
                        </div>
                    </div>
                    
                    <!-- Info -->
                    <div class="flex-1 min-w-0 w-full">
                        <div class="flex justify-between items-start">
                            <h4 class="font-bold text-white text-lg truncate pr-2"><?php echo htmlspecialchars($b['title']); ?></h4>
                            <div class="flex gap-2">
                                <a href="<?php echo admin_url('banners', ['delete' => $b['id']]); ?>" 
                                   class="text-slate-400 hover:text-red-400 p-2 transition bg-slate-900 hover:bg-red-900/20 rounded-lg border border-slate-700"
                                   onclick="return confirm('Delete this banner?')"
                                   title="Delete">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </div>
                        </div>

                        <?php if($b['target_url']): ?>
                            <a href="<?php echo htmlspecialchars($b['target_url']); ?>" target="_blank" class="text-blue-400 text-xs hover:underline truncate block mt-1 flex items-center gap-1">
                                <i class="fas fa-external-link-alt"></i> <?php echo htmlspecialchars($b['target_url']); ?>
                            </a>
                        <?php else: ?>
                            <span class="text-slate-500 text-xs flex items-center gap-1 mt-1"><i class="fas fa-link"></i> No link attached</span>
                        <?php endif; ?>
                        
                        <div class="mt-3 pt-3 border-t border-slate-700/50 flex items-center text-[10px] text-slate-500 gap-4">
                            <span><i class="far fa-calendar mr-1"></i> <?php echo date('M d, Y', strtotime($b['created_at'])); ?></span>
                            <span class="font-mono text-slate-600 truncate max-w-[150px]"><?php echo htmlspecialchars($b['image_path']); ?></span>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>