<?php
// admin/product_edit.php

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 1. Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    try {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price = (float) $_POST['price'];
        $sale_price = !empty($_POST['sale_price']) ? (float) $_POST['sale_price'] : NULL;
        $cat_id = (int) $_POST['category_id'];
        $region_id = !empty($_POST['region_id']) ? $_POST['region_id'] : NULL;
        $instruction = trim($_POST['user_instruction']);
        $delivery_type = $_POST['delivery_type'];
        
        // Handle Duration Days
        $duration_days = NULL;
        if (!empty($_POST['duration_days'])) {
            $duration_days = (int)$_POST['duration_days'];
        }
        
        // Delivery Data
        $universal_content = ($delivery_type === 'universal') ? trim($_POST['universal_content']) : NULL;
        
        $form_fields = NULL;
        if ($delivery_type === 'form' && !empty($_POST['form_fields_raw'])) {
            $fields = explode(',', $_POST['form_fields_raw']);
            $form_schema = [];
            foreach($fields as $f) {
                if(trim($f)) $form_schema[] = ['label' => trim($f), 'type' => 'text'];
            }
            $form_fields = !empty($form_schema) ? json_encode($form_schema) : NULL;
        }

        // Handle Image Update
        $image_sql_part = "";
        $params = [$cat_id, $region_id, $name, $description, $price, $sale_price, $delivery_type, $universal_content, $form_fields, $instruction, $duration_days];

        if (!empty($_FILES['image']['name'])) {
            // 1. Get old image path to delete later
            $stmt = $pdo->prepare("SELECT image_path FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $old_img = $stmt->fetchColumn();

            // 2. Upload new image
            $target_dir = "../uploads/products/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
            
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $filename = uniqid('prod_') . '.' . $ext;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_dir . $filename)) {
                $image_sql_part = ", image_path = ?";
                $params[] = "uploads/products/" . $filename;

                // 3. Delete old image if exists
                if ($old_img && file_exists("../" . $old_img)) {
                    unlink("../" . $old_img);
                }
            }
        }

        // Finalize Params
        $params[] = $id; 

        // Update Product Table
        $stmt = $pdo->prepare("
            UPDATE products SET 
            category_id = ?, region_id = ?, name = ?, description = ?, 
            price = ?, sale_price = ?, delivery_type = ?, 
            universal_content = ?, form_fields = ?, user_instruction = ?,
            duration_days = ?
            $image_sql_part
            WHERE id = ?
        ");
        $stmt->execute($params);

        // Update Instructions: Delete old, Insert new
        $pdo->prepare("DELETE FROM product_instructions WHERE product_id = ?")->execute([$id]);
        
        if (!empty($_POST['checkbox_instructions'])) {
            $checkboxes = explode("\n", $_POST['checkbox_instructions']);
            $stmt_ins = $pdo->prepare("INSERT INTO product_instructions (product_id, instruction_text) VALUES (?, ?)");
            foreach ($checkboxes as $chk) {
                if (trim($chk)) $stmt_ins->execute([$id, trim($chk)]);
            }
        }

        redirect(admin_url('products', ['success' => 'updated']));

    } catch (Exception $e) {
        $error = "Error updating product: " . $e->getMessage();
    }
}

// 2. Fetch Product Data
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    echo "<div class='p-4 text-red-400 bg-red-900/20 rounded border border-red-900/50'>Product not found. <a href='".admin_url('products')."' class='underline'>Back</a></div>";
    return;
}

// Prepare Form Fields for Display (JSON -> CSV)
$current_form_fields = '';
if ($product['form_fields']) {
    $schema = json_decode($product['form_fields'], true);
    if(is_array($schema)) {
        $labels = array_map(function($f) { return $f['label']; }, $schema);
        $current_form_fields = implode(', ', $labels);
    }
}

// Prepare Instructions for Display (DB -> Textarea)
$stmt = $pdo->prepare("SELECT instruction_text FROM product_instructions WHERE product_id = ?");
$stmt->execute([$id]);
$current_instructions = implode("\n", $stmt->fetchAll(PDO::FETCH_COLUMN));

// Fetch Dropdowns
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$regions = $pdo->query("SELECT * FROM regions ORDER BY name ASC")->fetchAll();
?>

<div class="max-w-5xl mx-auto">
    <div class="flex justify-between items-center mb-6 relative z-10">
        <h1 class="text-3xl font-bold text-white tracking-tight">Modify Asset</h1>
        <a href="<?php echo admin_url('products'); ?>" class="text-slate-400 hover:text-white text-sm flex items-center gap-2 transition bg-slate-800 px-4 py-2 rounded-lg border border-slate-700 shadow-sm"><i class="fas fa-arrow-left"></i> Return</a>
    </div>

    <?php if(isset($error)) echo "<div class='bg-red-500/20 text-red-400 p-4 rounded-xl border border-red-500/50 mb-6 shadow-lg flex items-center gap-3'><i class='fas fa-exclamation-triangle'></i> $error</div>"; ?>

    <div class="bg-slate-900/80 backdrop-blur p-6 md:p-8 rounded-3xl border border-[#00f0ff]/20 shadow-[0_10px_30px_rgba(0,0,0,0.5)] relative overflow-hidden">
        <!-- Neon Background Effect -->
        <div class="absolute -right-20 -top-20 w-48 h-48 bg-[#00f0ff]/5 rounded-full blur-3xl pointer-events-none"></div>

        <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 xl:grid-cols-2 gap-8 relative z-10">
            
            <!-- LEFT COLUMN: Basic Info -->
            <div class="space-y-5">
                
                <div class="bg-slate-800/50 p-5 rounded-2xl border border-slate-700 shadow-inner space-y-4">
                    <h4 class="text-[10px] font-black text-[#00f0ff] uppercase tracking-widest border-b border-slate-700/50 pb-2 flex items-center gap-2"><i class="fas fa-info-circle"></i> Core Details</h4>
                    
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 mb-1.5 uppercase tracking-wider ml-1">Product Name</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required class="w-full bg-slate-900 border border-slate-600 p-3 rounded-xl text-white focus:border-[#00f0ff] outline-none transition font-medium">
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 mb-1.5 uppercase tracking-wider ml-1">Description</label>
                        <textarea name="description" rows="3" class="w-full bg-slate-900 border border-slate-600 p-3 rounded-xl text-white text-sm focus:border-[#00f0ff] outline-none transition resize-none"><?php echo htmlspecialchars($product['description']); ?></textarea>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 mb-1.5 uppercase tracking-wider ml-1">Retail Price (Ks)</label>
                            <input type="number" name="price" value="<?php echo $product['price']; ?>" required class="w-full bg-slate-900 border border-slate-600 p-3 rounded-xl text-white font-mono focus:border-[#00f0ff] outline-none transition">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-yellow-500 mb-1.5 uppercase tracking-wider ml-1 flex items-center gap-1"><i class="fas fa-bolt"></i> Flash Sale Price</label>
                            <input type="number" name="sale_price" value="<?php echo $product['sale_price']; ?>" placeholder="Optional" class="w-full bg-yellow-900/10 border border-yellow-600/50 p-3 rounded-xl text-yellow-400 font-mono focus:border-yellow-400 outline-none transition">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 mb-1.5 uppercase tracking-wider ml-1">Classification</label>
                            <div class="relative">
                                <select name="category_id" required class="w-full bg-slate-900 border border-slate-600 p-3 rounded-xl text-white text-sm focus:border-[#00f0ff] outline-none appearance-none cursor-pointer">
                                    <?php foreach($categories as $c): ?>
                                        <option value="<?php echo $c['id']; ?>" <?php echo $c['id'] == $product['category_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
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
                                        <option value="<?php echo $r['id']; ?>" <?php echo $r['id'] == $product['region_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($r['name']); ?></option>
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
                    
                    <input type="hidden" name="duration_days" id="duration_input" value="<?php echo $product['duration_days']; ?>">
                    
                    <div class="grid grid-cols-3 sm:grid-cols-5 gap-2 mb-3" id="duration_pills">
                        <button type="button" onclick="setDuration(30, this)" class="dur-btn border border-slate-600 bg-slate-900 text-slate-400 hover:text-white hover:border-[#00f0ff] rounded-lg py-2 text-xs font-bold transition <?php echo $product['duration_days'] == 30 ? 'border-purple-500 bg-purple-900/30 text-purple-300 shadow-[0_0_15px_rgba(168,85,247,0.2)]' : ''; ?>">1 Month</button>
                        <button type="button" onclick="setDuration(90, this)" class="dur-btn border border-slate-600 bg-slate-900 text-slate-400 hover:text-white hover:border-[#00f0ff] rounded-lg py-2 text-xs font-bold transition <?php echo $product['duration_days'] == 90 ? 'border-purple-500 bg-purple-900/30 text-purple-300 shadow-[0_0_15px_rgba(168,85,247,0.2)]' : ''; ?>">3 Months</button>
                        <button type="button" onclick="setDuration(180, this)" class="dur-btn border border-slate-600 bg-slate-900 text-slate-400 hover:text-white hover:border-[#00f0ff] rounded-lg py-2 text-xs font-bold transition <?php echo $product['duration_days'] == 180 ? 'border-purple-500 bg-purple-900/30 text-purple-300 shadow-[0_0_15px_rgba(168,85,247,0.2)]' : ''; ?>">6 Months</button>
                        <button type="button" onclick="setDuration(365, this)" class="dur-btn border border-slate-600 bg-slate-900 text-slate-400 hover:text-white hover:border-[#00f0ff] rounded-lg py-2 text-xs font-bold transition <?php echo $product['duration_days'] == 365 ? 'border-purple-500 bg-purple-900/30 text-purple-300 shadow-[0_0_15px_rgba(168,85,247,0.2)]' : ''; ?>">1 Year</button>
                        <button type="button" onclick="setDuration(0, this)" class="dur-btn border border-slate-600 bg-slate-900 text-slate-400 hover:text-white hover:border-[#00f0ff] rounded-lg py-2 text-xs font-bold transition <?php echo empty($product['duration_days']) ? 'border-purple-500 bg-purple-900/30 text-purple-300 shadow-[0_0_15px_rgba(168,85,247,0.2)]' : ''; ?>">Lifetime</button>
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-slate-500 font-medium">Or custom days:</span>
                        <?php 
                            $is_custom = !in_array($product['duration_days'], [0, 30, 90, 180, 365]) && !empty($product['duration_days']);
                        ?>
                        <input type="number" id="custom_duration" oninput="setCustomDuration(this.value)" value="<?php echo $is_custom ? $product['duration_days'] : ''; ?>" placeholder="e.g. 45" class="bg-slate-900 border border-slate-600 rounded-lg px-3 py-1.5 text-white text-xs w-24 outline-none font-mono <?php echo $is_custom ? 'border-purple-500 text-purple-300 bg-purple-900/20' : 'focus:border-purple-400'; ?>">
                    </div>
                </div>

            </div>

            <!-- RIGHT COLUMN: Delivery & Rules -->
            <div class="space-y-5 flex flex-col h-full">
                
                <!-- Delivery Configuration -->
                <div class="bg-slate-800/50 p-5 rounded-2xl border border-slate-700 shadow-inner relative overflow-hidden">
                    <h4 class="text-[10px] font-black text-green-400 uppercase tracking-widest border-b border-slate-700/50 pb-2 mb-4 flex items-center gap-2 relative z-10"><i class="fas fa-truck-fast"></i> Fulfillment Protocol</h4>
                    
                    <div class="relative z-10">
                        <select id="delivery_type" name="delivery_type" onchange="toggleTypeFields()" class="w-full bg-slate-900 border border-slate-600 p-3 rounded-xl text-white mb-4 focus:border-green-400 outline-none font-bold text-sm shadow-inner appearance-none cursor-pointer">
                            <option value="universal" <?php echo $product['delivery_type'] == 'universal' ? 'selected' : ''; ?>>Universal Access (Same credentials for all buyers)</option>
                            <option value="unique" <?php echo $product['delivery_type'] == 'unique' ? 'selected' : ''; ?>>Unique Serial/Key (1 Key per Buyer)</option>
                            <option value="form" <?php echo $product['delivery_type'] == 'form' ? 'selected' : ''; ?>>Manual Provisioning (User submits target details)</option>
                        </select>
                        <i class="fas fa-chevron-down absolute right-4 top-4 text-slate-500 pointer-events-none text-xs"></i>
                    </div>

                    <!-- Type: Unique Alert -->
                    <div id="field_unique" class="hidden relative z-10 animate-fade-in-up">
                        <div class="text-xs text-yellow-500 bg-yellow-900/20 p-4 rounded-xl border border-yellow-500/20 flex items-center gap-3 shadow-inner">
                            <i class="fas fa-exclamation-triangle text-xl shrink-0"></i>
                            <span>To manage unique keys for this asset, please navigate to the <a href="<?php echo admin_url('keys', ['product_id' => $id]); ?>" class="underline hover:text-white font-bold tracking-wide">Stock Management</a> console.</span>
                        </div>
                    </div>

                    <!-- Type: Universal -->
                    <div id="field_universal" class="hidden space-y-2 relative z-10 animate-fade-in-up">
                        <label class="block text-[10px] font-bold text-slate-400 mb-1.5 uppercase tracking-wider ml-1">Universal Output Data</label>
                        <textarea name="universal_content" rows="4" class="w-full bg-slate-950 border border-slate-600 p-3 rounded-xl text-blue-400 text-sm font-mono focus:border-blue-400 outline-none shadow-inner resize-none"><?php echo htmlspecialchars($product['universal_content']); ?></textarea>
                    </div>

                    <!-- Type: Form -->
                    <div id="field_form" class="hidden space-y-2 relative z-10 animate-fade-in-up">
                        <label class="block text-[10px] font-bold text-slate-400 mb-1.5 uppercase tracking-wider ml-1">Required Input Fields (Comma Separated)</label>
                        <input type="text" name="form_fields_raw" value="<?php echo htmlspecialchars($current_form_fields); ?>" class="w-full bg-slate-950 border border-slate-600 p-3 rounded-xl text-yellow-400 text-sm focus:border-yellow-400 outline-none shadow-inner">
                    </div>
                </div>
                
                <!-- Instructions & Media -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 flex-1">
                    <div class="bg-slate-800/50 p-5 rounded-2xl border border-slate-700 shadow-inner flex flex-col">
                        <label class="block text-[10px] font-black text-red-400 uppercase tracking-widest mb-2 border-b border-slate-700/50 pb-2"><i class="fas fa-shield-alt"></i> Compliance Checkboxes</label>
                        <textarea name="checkbox_instructions" rows="4" class="w-full bg-slate-900 border border-slate-600 p-3 rounded-xl text-white text-xs focus:border-red-400 outline-none shadow-inner resize-none flex-1"><?php echo htmlspecialchars($current_instructions); ?></textarea>
                        <p class="text-[9px] text-slate-500 mt-2 font-medium">1 rule per line. User MUST check all to buy.</p>
                    </div>
                    
                    <div class="flex flex-col gap-5">
                        <div class="bg-slate-800/50 p-4 rounded-2xl border border-slate-700 shadow-inner">
                            <label class="block text-[10px] font-bold text-slate-400 mb-1.5 uppercase tracking-wider ml-1">Public Alert Note</label>
                            <textarea name="user_instruction" rows="2" class="w-full bg-slate-900 border border-slate-600 p-3 rounded-xl text-yellow-200 text-xs focus:border-yellow-400 outline-none shadow-inner resize-none"><?php echo htmlspecialchars($product['user_instruction']); ?></textarea>
                        </div>

                        <div class="bg-slate-800/50 p-4 rounded-2xl border border-slate-700 shadow-inner flex-1 flex flex-col justify-center">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Asset Image</label>
                            
                            <div class="flex items-center gap-3">
                                <?php if($product['image_path']): ?>
                                    <div class="w-14 h-14 rounded-xl border border-slate-600 bg-slate-900 shrink-0 overflow-hidden shadow-inner">
                                        <img src="<?php echo MAIN_SITE_URL . $product['image_path']; ?>" class="w-full h-full object-cover">
                                    </div>
                                <?php else: ?>
                                    <div class="w-14 h-14 rounded-xl bg-slate-900 border border-slate-700 flex items-center justify-center text-slate-500 shrink-0 shadow-inner"><i class="fas fa-image text-xl"></i></div>
                                <?php endif; ?>
                                
                                <div class="relative border-2 border-dashed border-slate-600 rounded-xl flex-1 h-14 flex flex-col items-center justify-center hover:bg-slate-700/50 hover:border-[#00f0ff]/50 transition-colors cursor-pointer group">
                                    <input type="file" name="image" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="document.getElementById('edit-file-label').innerHTML = `<span class='text-green-400 font-bold text-[10px] truncate max-w-full px-2'><i class='fas fa-check'></i> ` + this.files[0].name + `</span>`">
                                    <p id="edit-file-label" class="text-[10px] text-slate-400 group-hover:text-white transition-colors font-medium"><i class="fas fa-upload mr-1"></i> Replace Image</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Footer Action -->
            <div class="xl:col-span-2 pt-4 border-t border-slate-700/50 mt-2">
                <button type="submit" name="update_product" class="w-full bg-gradient-to-r from-blue-600 to-[#00f0ff] hover:from-blue-500 hover:to-[#00f0ff] text-slate-900 font-black py-4 rounded-2xl shadow-[0_0_20px_rgba(0,240,255,0.3)] transition transform active:scale-[0.99] flex justify-center items-center gap-2 uppercase tracking-widest text-sm">
                    <i class="fas fa-save"></i> Commit Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Delivery Type Logic
    function toggleTypeFields() {
        const type = document.getElementById('delivery_type').value;
        ['unique', 'universal', 'form'].forEach(t => {
            const el = document.getElementById('field_' + t);
            if(t === type) el.classList.remove('hidden');
            else el.classList.add('hidden');
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
            customDur.classList.remove('border-purple-500', 'text-purple-300', 'bg-purple-900/20');
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
</script>