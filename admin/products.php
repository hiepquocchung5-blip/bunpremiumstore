<?php
// admin/products.php

// 1. Handle POST: Add Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    try {
        $pdo->beginTransaction();

        $name = trim($_POST['name']);
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-'));
        $description = trim($_POST['description']);
        $price = (float) $_POST['price'];
        $sale_price = !empty($_POST['sale_price']) ? (float) $_POST['sale_price'] : NULL;
        $cat_id = (int) $_POST['category_id'];
        $region_id = !empty($_POST['region_id']) ? $_POST['region_id'] : NULL;
        $delivery_type = $_POST['delivery_type'];
        $instruction = trim($_POST['user_instruction']);
        $universal_content = ($delivery_type === 'universal') ? trim($_POST['universal_content']) : NULL;
        
        // Handle Duration Days (New Feature)
        $duration_days = NULL;
        if (!empty($_POST['duration_days'])) {
            $duration_days = (int)$_POST['duration_days'];
        }

        // Form Fields
        $form_fields = NULL;
        if ($delivery_type === 'form' && !empty($_POST['form_fields_raw'])) {
            $fields = explode(',', $_POST['form_fields_raw']);
            $form_schema = [];
            foreach($fields as $f) { if(trim($f)) $form_schema[] = ['label' => trim($f), 'type' => 'text']; }
            $form_fields = !empty($form_schema) ? json_encode($form_schema) : NULL;
        }

        // Image Upload Logic
        $db_image_path = NULL;
        if (!empty($_FILES['image']['name'])) {
            $target_dir = "../uploads/products/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
            
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $filename = uniqid('prod_') . '.' . $ext;
            $target_file = $target_dir . $filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $db_image_path = "uploads/products/" . $filename;
            }
        }

        // Insert Product (Now includes duration_days and slug)
        $stmt = $pdo->prepare("INSERT INTO products (category_id, region_id, name, slug, description, price, sale_price, delivery_type, universal_content, form_fields, user_instruction, duration_days, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$cat_id, $region_id, $name, $slug, $description, $price, $sale_price, $delivery_type, $universal_content, $form_fields, $instruction, $duration_days, $db_image_path]);
        $product_id = $pdo->lastInsertId();

        // Handle Keys
        if ($delivery_type === 'unique' && !empty($_POST['unique_keys'])) {
            $keys = explode("\n", $_POST['unique_keys']);
            $stmt_key = $pdo->prepare("INSERT INTO product_keys (product_id, key_content) VALUES (?, ?)");
            foreach ($keys as $k) { if (trim($k)) $stmt_key->execute([$product_id, trim($k)]); }
        }

        // Handle Checkboxes
        if (!empty($_POST['checkbox_instructions'])) {
            $checkboxes = explode("\n", $_POST['checkbox_instructions']);
            $stmt_ins = $pdo->prepare("INSERT INTO product_instructions (product_id, instruction_text) VALUES (?, ?)");
            foreach ($checkboxes as $chk) { if (trim($chk)) $stmt_ins->execute([$product_id, trim($chk)]); }
        }

        $pdo->commit();
        redirect(admin_url('products', ['success' => 1]));

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error adding product: " . $e->getMessage();
    }
}

// 2. Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Delete image file first
    $stmt = $pdo->prepare("SELECT image_path FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $img = $stmt->fetchColumn();
    if($img && file_exists("../".$img)) unlink("../".$img);

    $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
    redirect(admin_url('products', ['deleted' => 1]));
}

// 3. Fetch Data
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$regions = $pdo->query("SELECT * FROM regions ORDER BY name ASC")->fetchAll();
$products = $pdo->query("
    SELECT p.*, c.name as cat_name, c.image_url as cat_image,
    (SELECT COUNT(*) FROM product_keys WHERE product_id = p.id AND is_sold = 0) as stock_count
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    ORDER BY p.id DESC
")->fetchAll();
?>

<div class="mb-10">
    <h1 class="text-3xl font-extrabold text-white tracking-tight font-heading">Product Catalog</h1>
    <p class="text-slate-500 text-sm mt-2">Manage your inventory, pricing, and fulfillment settings.</p>
</div>

<?php if(isset($_GET['success'])): ?>
    <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-2xl mb-8 flex items-center gap-3 animate-fade-in shadow-sm">
        <div class="w-8 h-8 rounded-lg bg-emerald-500/20 flex items-center justify-center">
            <i class="fas fa-check-circle"></i>
        </div>
        <span class="text-sm font-bold">Product successfully deployed to the storefront.</span>
    </div>
<?php endif; ?>

<?php if(isset($error)): ?>
    <div class="bg-rose-500/10 border border-rose-500/20 text-rose-400 p-4 rounded-2xl mb-8 flex items-center gap-3 animate-fade-in shadow-sm">
        <div class="w-8 h-8 rounded-lg bg-rose-500/20 flex items-center justify-center">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <span class="text-sm font-bold"><?php echo htmlspecialchars($error); ?></span>
    </div>
<?php endif; ?>

<!-- Add Product Form -->
<div class="custom-card p-8 lg:p-10 mb-12 relative overflow-hidden">
    <div class="flex items-center gap-4 mb-10 border-b border-white/5 pb-6">
        <div class="w-12 h-12 rounded-2xl bg-indigo-600 flex items-center justify-center text-white shadow-lg shadow-indigo-500/20">
            <i class="fas fa-plus text-xl"></i>
        </div>
        <div>
            <h3 class="font-bold text-xl text-white font-heading">Create New Offering</h3>
            <p class="text-slate-500 text-xs mt-0.5">Define a new digital asset for your customers.</p>
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 xl:grid-cols-2 gap-10">
        
        <!-- Primary Information -->
        <div class="space-y-8">
            <div class="bg-black/20 p-6 rounded-3xl border border-white/5 space-y-6">
                <div class="flex items-center gap-2 text-indigo-400 mb-2">
                    <i class="fas fa-info-circle text-xs"></i>
                    <span class="text-[10px] font-bold uppercase tracking-[0.2em]">Core Details</span>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-2 uppercase tracking-wider ml-1">Product Title</label>
                        <input type="text" name="name" required placeholder="e.g. Premium Subscription Plan" class="w-full bg-slate-900/50 border border-white/5 p-4 rounded-2xl text-white focus:border-indigo-500 outline-none transition placeholder-slate-600 font-medium">
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-2 uppercase tracking-wider ml-1">Marketing Description</label>
                        <textarea name="description" rows="3" class="w-full bg-slate-900/50 border border-white/5 p-4 rounded-2xl text-white text-sm focus:border-indigo-500 outline-none transition placeholder-slate-600 resize-none" placeholder="Highlight key features and benefits..."></textarea>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-2 uppercase tracking-wider ml-1">Regular Price (Ks)</label>
                        <input type="number" name="price" required placeholder="0" class="w-full bg-slate-900/50 border border-white/5 p-4 rounded-2xl text-white font-bold focus:border-indigo-500 outline-none transition">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-emerald-500 mb-2 uppercase tracking-wider ml-1">Promotional Price</label>
                        <input type="number" name="sale_price" placeholder="Optional" class="w-full bg-emerald-500/5 border border-emerald-500/10 p-4 rounded-2xl text-emerald-400 font-bold focus:border-emerald-500 outline-none transition placeholder-emerald-900/30">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-2 uppercase tracking-wider ml-1">Category</label>
                        <div class="relative">
                            <select name="category_id" required class="w-full bg-slate-900/50 border border-white/5 p-4 rounded-2xl text-white text-sm focus:border-indigo-500 outline-none appearance-none cursor-pointer font-medium">
                                <?php foreach($categories as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fas fa-chevron-down absolute right-5 top-5 text-slate-600 pointer-events-none text-[10px]"></i>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-2 uppercase tracking-wider ml-1">Region Lock</label>
                        <div class="relative">
                            <select name="region_id" class="w-full bg-slate-900/50 border border-white/5 p-4 rounded-2xl text-white text-sm focus:border-indigo-500 outline-none appearance-none cursor-pointer font-medium">
                                <option value="">Global / No Lock</option>
                                <?php foreach($regions as $r): ?>
                                    <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fas fa-chevron-down absolute right-5 top-5 text-slate-600 pointer-events-none text-[10px]"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Duration Selection -->
            <div class="bg-black/20 p-6 rounded-3xl border border-white/5">
                <div class="flex items-center gap-2 text-purple-400 mb-4">
                    <i class="fas fa-clock text-xs"></i>
                    <span class="text-[10px] font-bold uppercase tracking-[0.2em]">Usage Duration</span>
                </div>
                
                <input type="hidden" name="duration_days" id="duration_input" value="">
                
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6" id="duration_pills">
                    <button type="button" onclick="setDuration(30, this)" class="dur-btn bg-slate-900/50 border border-white/5 text-slate-400 rounded-xl py-3 text-[11px] font-bold transition-all hover:border-indigo-500/50">1 Mo</button>
                    <button type="button" onclick="setDuration(90, this)" class="dur-btn bg-slate-900/50 border border-white/5 text-slate-400 rounded-xl py-3 text-[11px] font-bold transition-all hover:border-indigo-500/50">3 Mo</button>
                    <button type="button" onclick="setDuration(180, this)" class="dur-btn bg-slate-900/50 border border-white/5 text-slate-400 rounded-xl py-3 text-[11px] font-bold transition-all hover:border-indigo-500/50">6 Mo</button>
                    <button type="button" onclick="setDuration(365, this)" class="dur-btn bg-slate-900/50 border border-white/5 text-slate-400 rounded-xl py-3 text-[11px] font-bold transition-all hover:border-indigo-500/50">1 Yr</button>
                    <button type="button" onclick="setDuration(0, this)" class="dur-btn bg-slate-900/50 border border-white/5 text-slate-400 rounded-xl py-3 text-[11px] font-bold transition-all hover:border-indigo-500/50">Lifetime</button>
                </div>
                
                <div class="flex items-center gap-4 bg-slate-900/30 p-3 rounded-2xl border border-white/5">
                    <span class="text-[11px] text-slate-500 font-bold uppercase tracking-wider ml-2">Custom Days:</span>
                    <input type="number" id="custom_duration" oninput="setCustomDuration(this.value)" placeholder="Enter days" class="bg-slate-900 border border-white/5 rounded-xl px-4 py-2 text-white text-xs w-full focus:border-indigo-500 outline-none font-bold">
                </div>
            </div>
        </div>

        <!-- Fulfillment & Media -->
        <div class="space-y-8">
            <div class="bg-black/20 p-6 rounded-3xl border border-white/5">
                <div class="flex items-center gap-2 text-emerald-400 mb-6">
                    <i class="fas fa-truck-fast text-xs"></i>
                    <span class="text-[10px] font-bold uppercase tracking-[0.2em]">Fulfillment Protocol</span>
                </div>
                
                <div class="relative mb-6">
                    <select id="delivery_type" name="delivery_type" onchange="toggleTypeFields()" class="w-full bg-slate-900/50 border border-white/5 p-4 rounded-2xl text-white focus:border-emerald-500 outline-none font-bold text-sm appearance-none cursor-pointer">
                        <option value="universal">Universal Access (Static Credentials)</option>
                        <option value="unique">Unique Inventory (Serial / Account Keys)</option>
                        <option value="form">On-Demand Provisioning (User Requirements)</option>
                    </select>
                    <i class="fas fa-chevron-down absolute right-5 top-5 text-slate-600 pointer-events-none text-[10px]"></i>
                </div>
                
                <!-- Fulfillment Fields -->
                <div id="field_unique" class="hidden animate-fade-in">
                    <label class="block text-[11px] font-bold text-slate-500 mb-2 uppercase tracking-wider ml-1">Inventory Injection (1 per line)</label>
                    <textarea name="unique_keys" rows="4" class="w-full bg-slate-900/80 border border-white/5 p-4 rounded-2xl text-emerald-400 text-xs font-mono focus:border-emerald-500 outline-none resize-none placeholder-slate-700" placeholder="SERIAL-KEY-001&#10;SERIAL-KEY-002..."></textarea>
                </div>
                <div id="field_universal" class="animate-fade-in">
                    <label class="block text-[11px] font-bold text-slate-500 mb-2 uppercase tracking-wider ml-1">Static Access Data</label>
                    <textarea name="universal_content" rows="4" class="w-full bg-slate-900/80 border border-white/5 p-4 rounded-2xl text-indigo-400 text-xs font-mono focus:border-indigo-500 outline-none resize-none placeholder-slate-700" placeholder="User: premium_access&#10;Pass: secret123..."></textarea>
                </div>
                <div id="field_form" class="hidden animate-fade-in">
                    <label class="block text-[11px] font-bold text-slate-500 mb-2 uppercase tracking-wider ml-1">Requirement Fields (Comma Separated)</label>
                    <input type="text" name="form_fields_raw" class="w-full bg-slate-900/80 border border-white/5 p-4 rounded-2xl text-amber-400 text-sm font-bold focus:border-amber-500 outline-none placeholder-slate-700" placeholder="e.g. Account ID, In-game Name">
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="bg-black/20 p-6 rounded-3xl border border-white/5 flex flex-col">
                    <div class="flex items-center gap-2 text-rose-400 mb-4">
                        <i class="fas fa-shield-halved text-xs"></i>
                        <span class="text-[10px] font-bold uppercase tracking-[0.2em]">Compliance Rules</span>
                    </div>
                    <textarea name="checkbox_instructions" rows="4" placeholder="User must agree to terms...&#10;No refunds policy..." class="w-full bg-slate-900/50 border border-white/5 p-4 rounded-2xl text-white text-xs focus:border-rose-500 outline-none resize-none flex-1 placeholder-slate-700"></textarea>
                    <p class="text-[9px] text-slate-600 mt-3 font-bold uppercase tracking-widest">Mandatory check per line</p>
                </div>
                
                <div class="flex flex-col gap-8">
                    <div class="bg-black/20 p-6 rounded-3xl border border-white/5">
                        <label class="block text-[11px] font-bold text-slate-500 mb-2 uppercase tracking-wider">Purchase Notice</label>
                        <textarea name="user_instruction" rows="2" class="w-full bg-slate-900/50 border border-white/5 p-4 rounded-2xl text-amber-200 text-xs focus:border-amber-500 outline-none resize-none placeholder-slate-700" placeholder="Alert shown on product page..."></textarea>
                    </div>

                    <div class="bg-black/20 p-6 rounded-3xl border border-white/5 flex-1 flex flex-col">
                        <label class="block text-[11px] font-bold text-slate-500 mb-3 uppercase tracking-wider">Product Visual</label>
                        <div class="relative border-2 border-dashed border-white/5 rounded-2xl flex-1 flex flex-col items-center justify-center hover:bg-white/[0.02] hover:border-indigo-500/30 transition-all cursor-pointer group p-4">
                            <input type="file" name="image" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="document.getElementById('file-label').innerHTML = `<span class='text-emerald-400 font-bold text-[10px] uppercase tracking-widest truncate'><i class='fas fa-check mr-1'></i> ` + this.files[0].name + `</span>`">
                            <i class="fas fa-cloud-arrow-up text-2xl text-slate-600 mb-2 group-hover:text-indigo-400 transition-colors"></i>
                            <p id="file-label" class="text-[10px] font-bold text-slate-500 uppercase tracking-widest group-hover:text-slate-300">Upload Asset Image</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="xl:col-span-2 pt-6">
            <button type="submit" name="add_product" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-5 rounded-2xl shadow-xl shadow-indigo-600/20 transition transform active:scale-[0.98] flex justify-center items-center gap-3 uppercase tracking-[0.2em] text-sm group">
                <i class="fas fa-plus-circle group-hover:rotate-90 transition-transform duration-500"></i>
                <span>Deploy Product Offering</span>
            </button>
        </div>
    </form>
</div>

<!-- Product Table -->
<div class="custom-card overflow-hidden">
    <div class="p-8 border-b border-white/5 bg-white/[0.02] flex justify-between items-center">
        <div>
            <h3 class="font-bold text-xl text-white font-heading">Active Catalog</h3>
            <p class="text-slate-500 text-xs mt-1"><?php echo count($products); ?> Products live on storefront</p>
        </div>
        <div class="w-10 h-10 rounded-xl bg-slate-800 flex items-center justify-center text-slate-500">
            <i class="fas fa-layer-group"></i>
        </div>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="bg-black/20 text-slate-500 uppercase text-[10px] font-bold tracking-[0.2em]">
                    <th class="p-6 pl-10">Asset</th>
                    <th class="p-6">Classification</th>
                    <th class="p-6">Slug</th>
                    <th class="p-6 text-center">Inventory</th>
                    <th class="p-6 text-center">Duration</th>
                    <th class="p-6 text-right">MSRP</th>
                    <th class="p-6 text-right">Promotion</th>
                    <th class="p-6 pr-10 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/[0.03]">
                <?php foreach($products as $p): ?>
                    <tr class="hover:bg-indigo-500/[0.02] transition-colors group">
                        <td class="p-6 pl-10">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-2xl bg-slate-900 border border-white/5 flex items-center justify-center overflow-hidden relative shadow-inner">
                                    <?php if($p['image_path']): ?>
                                        <img src="<?php echo MAIN_SITE_URL . $p['image_path']; ?>" class="w-full h-full object-cover">
                                    <?php elseif(!empty($p['cat_image'])): ?>
                                        <img src="<?php echo MAIN_SITE_URL . $p['cat_image']; ?>" class="w-full h-full object-cover opacity-60">
                                    <?php else: ?>
                                        <i class="fas fa-cube text-xl text-slate-700"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="flex flex-col">
                                    <span class="font-bold text-white group-hover:text-indigo-400 transition-colors"><?php echo htmlspecialchars($p['name']); ?></span>
                                    <span class="text-[10px] text-slate-500 font-bold uppercase tracking-widest flex items-center gap-1.5 mt-0.5">
                                        <i class="fas fa-truck-fast text-[8px]"></i> <?php echo $p['delivery_type']; ?>
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td class="p-6">
                            <div class="flex flex-col gap-1">
                                <span class="bg-slate-800/50 text-slate-400 px-3 py-1 rounded-lg text-[9px] font-bold uppercase tracking-widest border border-white/5 w-fit">
                                    <?php echo htmlspecialchars($p['cat_name']); ?>
                                </span>
                                <?php if($p['region_id']): ?>
                                    <span class="text-[9px] text-indigo-500 font-bold uppercase tracking-widest flex items-center gap-1 ml-1">
                                        <i class="fas fa-globe-asia"></i> Locked
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="p-6">
                            <div class="flex items-center gap-2">
                                <span class="bg-slate-900 border border-white/5 text-slate-400 px-3 py-1.5 rounded-lg font-mono text-xs select-all">
                                    <?php echo htmlspecialchars($p['slug']); ?>
                                </span>
                                <button type="button" onclick="copySlugToClipboard(this, '<?php echo htmlspecialchars($p['slug']); ?>')" class="w-8 h-8 rounded-lg bg-slate-800 hover:bg-indigo-600 text-slate-400 hover:text-white transition flex items-center justify-center border border-white/5" title="Copy Slug">
                                    <i class="fas fa-copy text-xs"></i>
                                </button>
                            </div>
                        </td>
                        <td class="p-6 text-center">
                            <?php if($p['delivery_type'] == 'unique'): ?>
                                <?php 
                                    $stockColor = $p['stock_count'] > 5 ? 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20' : 
                                                 ($p['stock_count'] > 0 ? 'text-amber-400 bg-amber-500/10 border-amber-500/20' : 'text-rose-400 bg-rose-500/10 border-rose-500/20 animate-pulse');
                                ?>
                                <a href="<?php echo admin_url('keys', ['product_id' => $p['id']]); ?>" class="inline-flex items-center justify-center px-4 py-2 rounded-xl border <?php echo $stockColor; ?> font-bold text-xs transition-all hover:scale-105" title="Manage Keys">
                                    <?php echo $p['stock_count']; ?> Units
                                </a>
                            <?php else: ?>
                                <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-slate-800/50 border border-white/5 text-slate-600">
                                    <i class="fas fa-infinity text-xs"></i>
                                </span>
                            <?php endif; ?>
                        </td>
                        
                        <td class="p-6 text-center">
                            <?php if($p['duration_days']): ?>
                                <span class="bg-purple-500/10 text-purple-400 border border-purple-500/20 px-3 py-1.5 rounded-xl text-[10px] font-bold uppercase tracking-widest">
                                    <?php echo $p['duration_days']; ?> Days
                                </span>
                            <?php else: ?>
                                <span class="text-slate-600 text-[10px] font-bold uppercase tracking-widest">Lifetime</span>
                            <?php endif; ?>
                        </td>

                        <td class="p-6 text-right font-bold text-white tracking-tight"><?php echo format_admin_currency($p['price']); ?></td>
                        
                        <td class="p-6 text-right">
                            <?php if($p['sale_price']): ?>
                                <span class="text-emerald-400 font-bold bg-emerald-500/10 px-3 py-1.5 rounded-xl border border-emerald-500/20 text-xs">
                                    <?php echo format_admin_currency($p['sale_price']); ?>
                                </span>
                            <?php else: ?>
                                <span class="text-slate-700 text-xs">-</span>
                            <?php endif; ?>
                        </td>
                        
                        <td class="p-6 pr-10 text-right">
                            <div class="flex justify-end gap-3">
                                <a href="<?php echo admin_url('product_edit', ['id' => $p['id']]); ?>" class="w-10 h-10 rounded-xl bg-slate-800/50 border border-white/5 text-indigo-400 hover:bg-indigo-600 hover:text-white hover:border-indigo-500 transition-all flex items-center justify-center shadow-sm" title="Modify Configuration">
                                    <i class="fas fa-pen-to-square text-xs"></i>
                                </a>
                                <a href="<?php echo admin_url('products', ['delete' => $p['id']]); ?>" 
                                   class="w-10 h-10 rounded-xl bg-slate-800/50 border border-white/5 text-rose-500 hover:bg-rose-600 hover:text-white hover:border-rose-500 transition-all flex items-center justify-center shadow-sm"
                                   onclick="return confirm('Archive Confirmation: Are you sure you want to remove this product and all associated data?')" title="Delete Forever">
                                   <i class="fas fa-trash-can text-xs"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                
                <?php if(empty($products)): ?>
                    <tr>
                        <td colspan="8" class="p-20 text-center">
                            <div class="flex flex-col items-center opacity-30">
                                <i class="fas fa-box-open text-6xl mb-6"></i>
                                <p class="text-lg font-bold uppercase tracking-[0.2em] text-slate-500 font-heading">Empty Catalog</p>
                                <p class="text-sm mt-2 font-medium text-slate-500">Deploy your first product to see it here.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Delivery Type Logic
    function toggleTypeFields() {
        const type = document.getElementById('delivery_type').value;
        ['unique', 'universal', 'form'].forEach(t => {
            const el = document.getElementById('field_' + t);
            if(t === type) {
                el.classList.remove('hidden');
            } else {
                el.classList.add('hidden');
            }
        });
    }
    toggleTypeFields();

    // Duration Logic
    const durInput = document.getElementById('duration_input');
    const customDur = document.getElementById('custom_duration');
    const durBtns = document.querySelectorAll('.dur-btn');

    function setDuration(days, btnElement) {
        // Update hidden input
        durInput.value = days > 0 ? days : '';
        
        // Reset custom input if a preset is clicked
        if(days !== 'custom') {
            customDur.value = '';
        }

        // Reset visual state for all buttons
        durBtns.forEach(btn => {
            btn.className = "dur-btn bg-slate-900/50 border border-white/5 text-slate-400 rounded-xl py-3 text-[11px] font-bold transition-all hover:border-indigo-500/50";
        });
        
        // Set active state if a button was clicked
        if(btnElement) {
            btnElement.className = "dur-btn bg-indigo-600 text-white border-indigo-500 rounded-xl py-3 text-[11px] font-bold transition-all shadow-lg shadow-indigo-600/20";
        }
    }

    function setCustomDuration(val) {
        const days = parseInt(val);
        if(days > 0) {
            setDuration('custom', null); // Clear presets
            durInput.value = days;
            customDur.classList.add('border-indigo-500', 'text-indigo-400', 'bg-indigo-500/10');
        } else {
            durInput.value = '';
            customDur.classList.remove('border-indigo-500', 'text-indigo-400', 'bg-indigo-500/10');
            // Select Lifetime by default if custom is cleared
            setDuration(0, durBtns[4]);
        }
    }
    
    // Initialize default (Lifetime)
    setDuration(0, durBtns[4]);

    // Copy Slug helper
    function copySlugToClipboard(btn, slug) {
        navigator.clipboard.writeText(slug).then(() => {
            const icon = btn.querySelector('i');
            icon.className = 'fas fa-check text-emerald-400';
            const originalClass = btn.className;
            btn.className = btn.className.replace('text-slate-400', 'text-emerald-400 border-emerald-500/30 bg-emerald-500/10');
            setTimeout(() => {
                icon.className = 'fas fa-copy';
                btn.className = originalClass;
            }, 2000);
        }).catch(err => {
            console.error('Failed to copy text: ', err);
        });
    }
</script>