<?php
// admin/products.php

// 1. Handle POST: Add Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    try {
        $pdo->beginTransaction();

        $name = trim($_POST['name']);
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

        // Insert Product (Now includes duration_days)
        $stmt = $pdo->prepare("INSERT INTO products (category_id, region_id, name, description, price, sale_price, delivery_type, universal_content, form_fields, user_instruction, duration_days, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$cat_id, $region_id, $name, $description, $price, $sale_price, $delivery_type, $universal_content, $form_fields, $instruction, $duration_days, $db_image_path]);
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

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-3xl font-bold text-white tracking-tight">Product Matrix</h1>
        <p class="text-slate-400 text-sm mt-1">Deploy inventory, adjust pricing, and configure delivery protocols.</p>
    </div>
</div>

<?php if(isset($_GET['success'])): ?>
    <div class="bg-green-500/10 border border-green-500/30 text-green-400 p-4 rounded-xl mb-6 flex items-center gap-3 animate-pulse shadow-[0_0_15px_rgba(34,197,94,0.15)]">
        <i class="fas fa-check-circle"></i> Asset deployed to the matrix successfully.
    </div>
<?php endif; ?>

<?php if(isset($error)): ?>
    <div class="bg-red-500/10 border border-red-500/30 text-red-400 p-4 rounded-xl mb-6 flex items-center gap-3 shadow-[0_0_15px_rgba(239,68,68,0.15)]">
        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<!-- Add Product Form -->
<div class="bg-slate-900/80 backdrop-blur border border-[#00f0ff]/20 p-6 rounded-3xl mb-8 shadow-[0_10px_30px_rgba(0,0,0,0.5)] relative overflow-hidden">
    <!-- Neon Background Effect -->
    <div class="absolute -right-20 -top-20 w-48 h-48 bg-[#00f0ff]/5 rounded-full blur-3xl pointer-events-none"></div>

    <div class="flex items-center gap-3 mb-6 border-b border-slate-700/50 pb-4 relative z-10">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-600 to-[#00f0ff] flex items-center justify-center text-slate-900 shadow-[0_0_15px_rgba(0,240,255,0.3)]">
            <i class="fas fa-plus text-lg"></i>
        </div>
        <h3 class="font-black text-xl text-white tracking-tight">Initialize New Product</h3>
    </div>

    <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 xl:grid-cols-2 gap-8 relative z-10">
        
        <!-- Basic Info Column -->
        <div class="space-y-5">
            
            <div class="bg-slate-800/50 p-5 rounded-2xl border border-slate-700 shadow-inner space-y-4">
                <h4 class="text-[10px] font-black text-[#00f0ff] uppercase tracking-widest border-b border-slate-700/50 pb-2 flex items-center gap-2"><i class="fas fa-info-circle"></i> Core Details</h4>
                
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 mb-1.5 uppercase tracking-wider ml-1">Product Name</label>
                    <input type="text" name="name" required placeholder="e.g. Netflix 4K UHD" class="w-full bg-slate-900 border border-slate-600 p-3 rounded-xl text-white focus:border-[#00f0ff] outline-none transition placeholder-slate-600 font-medium">
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-400 mb-1.5 uppercase tracking-wider ml-1">Description</label>
                    <textarea name="description" rows="2" class="w-full bg-slate-900 border border-slate-600 p-3 rounded-xl text-white text-sm focus:border-[#00f0ff] outline-none transition placeholder-slate-600 resize-none" placeholder="Provide detailed features and benefits..."></textarea>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 mb-1.5 uppercase tracking-wider ml-1">Retail Price (Ks)</label>
                        <input type="number" name="price" required placeholder="0" class="w-full bg-slate-900 border border-slate-600 p-3 rounded-xl text-white font-mono focus:border-[#00f0ff] outline-none transition">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-yellow-500 mb-1.5 uppercase tracking-wider ml-1 flex items-center gap-1"><i class="fas fa-bolt"></i> Flash Sale Price</label>
                        <input type="number" name="sale_price" placeholder="Optional" class="w-full bg-yellow-900/10 border border-yellow-600/50 p-3 rounded-xl text-yellow-400 font-mono focus:border-yellow-400 outline-none transition">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 mb-1.5 uppercase tracking-wider ml-1">Classification</label>
                        <div class="relative">
                            <select name="category_id" required class="w-full bg-slate-900 border border-slate-600 p-3 rounded-xl text-white text-sm focus:border-[#00f0ff] outline-none appearance-none cursor-pointer">
                                <?php foreach($categories as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fas fa-chevron-down absolute right-4 top-4 text-slate-500 pointer-events-none text-xs"></i>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 mb-1.5 uppercase tracking-wider ml-1">Geographic Region</label>
                        <div class="relative">
                            <select name="region_id" class="w-full bg-slate-900 border border-slate-600 p-3 rounded-xl text-white text-sm focus:border-[#00f0ff] outline-none appearance-none cursor-pointer">
                                <option value="">Global Network</option>
                                <?php foreach($regions as $r): ?>
                                    <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fas fa-chevron-down absolute right-4 top-4 text-slate-500 pointer-events-none text-xs"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Duration Selection UI -->
            <div class="bg-slate-800/50 p-5 rounded-2xl border border-slate-700 shadow-inner">
                <label class="block text-[10px] font-black text-purple-400 uppercase tracking-widest border-b border-slate-700/50 pb-2 mb-3 flex items-center gap-2"><i class="fas fa-clock"></i> License Duration</label>
                
                <!-- Hidden Input to store the actual value -->
                <input type="hidden" name="duration_days" id="duration_input" value="">
                
                <div class="grid grid-cols-3 sm:grid-cols-5 gap-2 mb-3" id="duration_pills">
                    <button type="button" onclick="setDuration(30, this)" class="dur-btn border border-slate-600 bg-slate-900 text-slate-400 hover:text-white hover:border-[#00f0ff] rounded-lg py-2 text-xs font-bold transition">1 Month</button>
                    <button type="button" onclick="setDuration(90, this)" class="dur-btn border border-slate-600 bg-slate-900 text-slate-400 hover:text-white hover:border-[#00f0ff] rounded-lg py-2 text-xs font-bold transition">3 Months</button>
                    <button type="button" onclick="setDuration(180, this)" class="dur-btn border border-slate-600 bg-slate-900 text-slate-400 hover:text-white hover:border-[#00f0ff] rounded-lg py-2 text-xs font-bold transition">6 Months</button>
                    <button type="button" onclick="setDuration(365, this)" class="dur-btn border border-slate-600 bg-slate-900 text-slate-400 hover:text-white hover:border-[#00f0ff] rounded-lg py-2 text-xs font-bold transition">1 Year</button>
                    <button type="button" onclick="setDuration(0, this)" class="dur-btn border border-slate-600 bg-slate-900 text-slate-400 hover:text-white hover:border-[#00f0ff] rounded-lg py-2 text-xs font-bold transition">Lifetime</button>
                </div>
                
                <div class="flex items-center gap-2">
                    <span class="text-xs text-slate-500 font-medium">Or custom days:</span>
                    <input type="number" id="custom_duration" oninput="setCustomDuration(this.value)" placeholder="e.g. 45" class="bg-slate-900 border border-slate-600 rounded-lg px-3 py-1.5 text-white text-xs w-24 focus:border-purple-400 outline-none font-mono">
                </div>
            </div>

        </div>

        <!-- Configuration & Delivery Column -->
        <div class="space-y-5 flex flex-col h-full">
            
            <!-- Delivery Logic -->
            <div class="bg-slate-800/50 p-5 rounded-2xl border border-slate-700 shadow-inner relative overflow-hidden">
                <h4 class="text-[10px] font-black text-green-400 uppercase tracking-widest border-b border-slate-700/50 pb-2 mb-4 flex items-center gap-2 relative z-10"><i class="fas fa-truck-fast"></i> Fulfillment Protocol</h4>
                
                <div class="relative z-10">
                    <select id="delivery_type" name="delivery_type" onchange="toggleTypeFields()" class="w-full bg-slate-900 border border-slate-600 p-3 rounded-xl text-white mb-4 focus:border-green-400 outline-none font-bold text-sm shadow-inner appearance-none cursor-pointer">
                        <option value="universal">Universal Access (Same credentials for all buyers)</option>
                        <option value="unique">Unique Serial/Key (1 Key per Buyer)</option>
                        <option value="form">Manual Provisioning (User submits target details)</option>
                    </select>
                    <i class="fas fa-chevron-down absolute right-4 top-4 text-slate-500 pointer-events-none text-xs"></i>
                </div>
                
                <!-- Dynamic Fields -->
                <div id="field_unique" class="hidden relative z-10 animate-fade-in-up">
                    <label class="block text-[10px] font-bold text-slate-400 mb-1.5 uppercase tracking-wider ml-1">Inject Initial Keys (1 per line)</label>
                    <textarea name="unique_keys" rows="3" class="w-full bg-slate-950 border border-slate-600 p-3 rounded-xl text-green-400 text-sm font-mono focus:border-green-400 outline-none shadow-inner resize-none placeholder-slate-700" placeholder="XXXX-XXXX-XXXX..."></textarea>
                </div>
                <div id="field_universal" class="relative z-10 animate-fade-in-up">
                    <label class="block text-[10px] font-bold text-slate-400 mb-1.5 uppercase tracking-wider ml-1">Universal Output Data</label>
                    <textarea name="universal_content" rows="3" class="w-full bg-slate-950 border border-slate-600 p-3 rounded-xl text-blue-400 text-sm font-mono focus:border-blue-400 outline-none shadow-inner resize-none placeholder-slate-700" placeholder="Email: admin@... Pass: 1234..."></textarea>
                </div>
                <div id="field_form" class="hidden relative z-10 animate-fade-in-up">
                    <label class="block text-[10px] font-bold text-slate-400 mb-1.5 uppercase tracking-wider ml-1">Required Input Fields (Comma Separated)</label>
                    <input type="text" name="form_fields_raw" class="w-full bg-slate-950 border border-slate-600 p-3 rounded-xl text-yellow-400 text-sm focus:border-yellow-400 outline-none shadow-inner placeholder-slate-700" placeholder="e.g. Riot ID, Current Password">
                </div>
            </div>
            
            <!-- Instructions & Media -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 flex-1">
                <div class="bg-slate-800/50 p-5 rounded-2xl border border-slate-700 shadow-inner flex flex-col">
                    <label class="block text-[10px] font-black text-red-400 uppercase tracking-widest mb-2 border-b border-slate-700/50 pb-2"><i class="fas fa-shield-alt"></i> Compliance Checkboxes</label>
                    <textarea name="checkbox_instructions" rows="4" placeholder="I agree to not change the password.&#10;I understand this is non-refundable." class="w-full bg-slate-900 border border-slate-600 p-3 rounded-xl text-white text-xs focus:border-red-400 outline-none shadow-inner resize-none flex-1 placeholder-slate-600"></textarea>
                    <p class="text-[9px] text-slate-500 mt-2 font-medium">1 rule per line. User MUST check all to buy.</p>
                </div>
                
                <div class="flex flex-col gap-5">
                    <div class="bg-slate-800/50 p-4 rounded-2xl border border-slate-700 shadow-inner">
                        <label class="block text-[10px] font-bold text-slate-400 mb-1.5 uppercase tracking-wider ml-1">Public Alert Note</label>
                        <textarea name="user_instruction" rows="2" class="w-full bg-slate-900 border border-slate-600 p-3 rounded-xl text-yellow-200 text-xs focus:border-yellow-400 outline-none shadow-inner resize-none placeholder-slate-600" placeholder="Displayed on product page..."></textarea>
                    </div>

                    <div class="bg-slate-800/50 p-4 rounded-2xl border border-slate-700 shadow-inner flex-1 flex flex-col justify-center">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Asset Image</label>
                        <div class="relative border-2 border-dashed border-slate-600 rounded-xl flex-1 flex flex-col items-center justify-center hover:bg-slate-700/50 hover:border-[#00f0ff]/50 transition-colors cursor-pointer group">
                            <input type="file" name="image" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="document.getElementById('file-label').innerHTML = `<span class='text-green-400 font-bold text-xs truncate max-w-full px-2'><i class='fas fa-check'></i> ` + this.files[0].name + `</span>`">
                            <i class="fas fa-camera text-2xl text-slate-500 mb-1 group-hover:text-[#00f0ff] transition-colors transform group-hover:scale-110 duration-300"></i>
                            <p id="file-label" class="text-[10px] text-slate-400 group-hover:text-white transition-colors">Tap to assign visual</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Submit Button -->
        <div class="xl:col-span-2 mt-2">
            <button type="submit" name="add_product" class="w-full bg-gradient-to-r from-blue-600 to-[#00f0ff] hover:from-blue-500 hover:to-[#00f0ff] text-slate-900 font-black py-4 rounded-2xl shadow-[0_0_20px_rgba(0,240,255,0.3)] transition transform active:scale-[0.99] flex justify-center items-center gap-2 uppercase tracking-widest text-sm group">
                <i class="fas fa-rocket group-hover:animate-pulse"></i> Deploy Asset to Store
            </button>
        </div>
    </form>
</div>

<!-- Product Matrix Table -->
<div class="bg-slate-900/60 backdrop-blur rounded-3xl border border-slate-700 overflow-hidden shadow-2xl flex flex-col">
    <div class="p-5 border-b border-slate-700/80 bg-slate-800/40 flex justify-between items-center shrink-0">
        <h3 class="font-bold text-white text-lg flex items-center gap-2"><i class="fas fa-database text-slate-400"></i> Active Inventory</h3>
        <span class="bg-slate-800 border border-slate-600 px-3 py-1 rounded-lg text-xs font-bold text-slate-300"><?php echo count($products); ?> Nodes</span>
    </div>
    
    <div class="overflow-x-auto flex-grow custom-scrollbar">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-800/60 text-slate-400 uppercase text-[10px] font-bold tracking-widest sticky top-0 z-20 backdrop-blur">
                <tr>
                    <th class="p-4 pl-6">Visual</th>
                    <th class="p-4">Designation</th>
                    <th class="p-4 text-center">Stock</th>
                    <th class="p-4 text-center">Duration</th>
                    <th class="p-4 text-right">Value (Ks)</th>
                    <th class="p-4 text-right">Flash Sale</th>
                    <th class="p-4 text-right pr-6">Commands</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/50">
                <?php foreach($products as $p): ?>
                    <tr class="hover:bg-slate-800/40 transition-colors group">
                        <td class="p-4 pl-6">
                            <div class="w-12 h-12 rounded-xl bg-slate-900 flex items-center justify-center text-[#00f0ff] border border-slate-700 shadow-inner overflow-hidden relative">
                                <?php if($p['image_path']): ?>
                                    <img src="<?php echo MAIN_SITE_URL . $p['image_path']; ?>" class="w-full h-full object-cover">
                                <?php elseif(!empty($p['cat_image'])): ?>
                                    <img src="<?php echo MAIN_SITE_URL . $p['cat_image']; ?>" class="w-full h-full object-cover opacity-60">
                                <?php else: ?>
                                    <i class="fas fa-cube text-xl opacity-50"></i>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="p-4">
                            <div class="font-bold text-white text-sm truncate max-w-[200px] group-hover:text-[#00f0ff] transition-colors" title="<?php echo htmlspecialchars($p['name']); ?>"><?php echo htmlspecialchars($p['name']); ?></div>
                            <div class="text-[10px] text-slate-400 font-mono mt-1 flex items-center gap-1.5">
                                <span class="bg-slate-800 px-1.5 py-0.5 rounded border border-slate-700 uppercase tracking-wide"><?php echo htmlspecialchars($p['cat_name']); ?></span>
                                <span class="uppercase tracking-widest text-blue-400"><i class="fas fa-exchange-alt mr-0.5"></i> <?php echo $p['delivery_type']; ?></span>
                            </div>
                        </td>
                        <td class="p-4 text-center align-middle">
                            <?php if($p['delivery_type'] == 'unique'): ?>
                                <?php 
                                    $stockColor = $p['stock_count'] > 5 ? 'text-green-400 bg-green-500/10 border-green-500/20' : 
                                                 ($p['stock_count'] > 0 ? 'text-yellow-400 bg-yellow-500/10 border-yellow-500/20' : 'text-red-400 bg-red-500/10 border-red-500/20 animate-pulse');
                                ?>
                                <a href="<?php echo admin_url('keys', ['product_id' => $p['id']]); ?>" class="inline-flex items-center justify-center w-10 h-10 rounded-xl border <?php echo $stockColor; ?> font-mono font-bold shadow-sm hover:scale-110 transition-transform text-sm" title="Manage Keys">
                                    <?php echo $p['stock_count']; ?>
                                </a>
                            <?php else: ?>
                                <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-slate-800 border border-slate-700 text-slate-500 text-lg">
                                    &infin;
                                </span>
                            <?php endif; ?>
                        </td>
                        
                        <!-- NEW: Duration Column -->
                        <td class="p-4 text-center align-middle">
                            <?php if($p['duration_days']): ?>
                                <span class="bg-purple-900/20 text-purple-400 border border-purple-500/30 px-2 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider whitespace-nowrap">
                                    <?php echo $p['duration_days']; ?> Days
                                </span>
                            <?php else: ?>
                                <span class="bg-slate-800 text-slate-400 border border-slate-700 px-2 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider">
                                    Lifetime
                                </span>
                            <?php endif; ?>
                        </td>

                        <td class="p-4 text-right align-middle font-mono font-bold text-slate-200"><?php echo format_admin_currency($p['price']); ?></td>
                        
                        <td class="p-4 text-right align-middle">
                            <?php if($p['sale_price']): ?>
                                <span class="font-mono text-yellow-400 font-bold bg-yellow-900/20 px-2 py-1 rounded border border-yellow-500/30 shadow-[0_0_10px_rgba(234,179,8,0.1)]">
                                    <?php echo format_admin_currency($p['sale_price']); ?>
                                </span>
                            <?php else: ?>
                                <span class="text-slate-600">-</span>
                            <?php endif; ?>
                        </td>
                        
                        <td class="p-4 text-right pr-6 align-middle">
                            <div class="flex justify-end gap-2">
                                <a href="<?php echo admin_url('product_edit', ['id' => $p['id']]); ?>" class="w-8 h-8 rounded-lg bg-slate-800 border border-slate-700 text-blue-400 hover:text-white hover:bg-blue-600 hover:border-blue-500 transition-all flex items-center justify-center shadow-sm" title="Edit Properties">
                                    <i class="fas fa-edit text-xs"></i>
                                </a>
                                <a href="<?php echo admin_url('products', ['delete' => $p['id']]); ?>" 
                                   class="w-8 h-8 rounded-lg bg-slate-800 border border-slate-700 text-red-400 hover:text-white hover:bg-red-600 hover:border-red-500 transition-all flex items-center justify-center shadow-sm"
                                   onclick="return confirm('CRITICAL WARNING: Terminate this asset? All associated keys and instructions will be lost.')" title="Purge Asset">
                                   <i class="fas fa-trash text-xs"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                
                <?php if(empty($products)): ?>
                    <tr>
                        <td colspan="7" class="p-12 text-center text-slate-500">
                            <i class="fas fa-box-open text-4xl mb-3 opacity-30"></i>
                            <p class="font-medium tracking-wide">Matrix is empty. Deploy a new asset to begin.</p>
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
            btn.className = "dur-btn border border-slate-600 bg-slate-900 text-slate-400 hover:text-white hover:border-[#00f0ff] rounded-lg py-2 text-xs font-bold transition";
        });
        
        // Set active state if a button was clicked
        if(btnElement) {
            btnElement.className = "dur-btn border border-purple-500 bg-purple-900/30 text-purple-300 rounded-lg py-2 text-xs font-bold transition shadow-[0_0_15px_rgba(168,85,247,0.2)]";
        }
    }

    function setCustomDuration(val) {
        const days = parseInt(val);
        if(days > 0) {
            setDuration('custom', null); // Clear presets
            durInput.value = days;
            customDur.classList.add('border-purple-500', 'text-purple-300', 'bg-purple-900/20');
        } else {
            durInput.value = '';
            customDur.classList.remove('border-purple-500', 'text-purple-300', 'bg-purple-900/20');
            // Select Lifetime by default if custom is cleared
            setDuration(0, durBtns[4]);
        }
    }
    
    // Initialize default (Lifetime)
    setDuration(0, durBtns[4]);
</script>