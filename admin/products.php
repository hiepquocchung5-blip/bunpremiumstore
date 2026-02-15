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

        // Insert
        $stmt = $pdo->prepare("INSERT INTO products (category_id, region_id, name, description, price, sale_price, delivery_type, universal_content, form_fields, user_instruction, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$cat_id, $region_id, $name, $description, $price, $sale_price, $delivery_type, $universal_content, $form_fields, $instruction, $db_image_path]);
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
    SELECT p.*, c.name as cat_name,
    (SELECT COUNT(*) FROM product_keys WHERE product_id = p.id AND is_sold = 0) as stock_count
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    ORDER BY p.id DESC
")->fetchAll();
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-3xl font-bold text-white">Products</h1>
        <p class="text-slate-400 text-sm mt-1">Manage inventory, prices, and delivery settings.</p>
    </div>
</div>

<?php if(isset($_GET['success'])): ?>
    <div class="bg-green-500/20 text-green-400 p-4 rounded-xl border border-green-500/50 mb-6 flex items-center gap-3 animate-pulse">
        <i class="fas fa-check-circle"></i> Product created successfully.
    </div>
<?php endif; ?>

<?php if(isset($error)): ?>
    <div class="bg-red-500/20 text-red-400 p-4 rounded-xl border border-red-500/50 mb-6 flex items-center gap-3">
        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
    </div>
<?php endif; ?>

<!-- Add Product Form -->
<div class="bg-slate-800 p-6 rounded-xl border border-slate-700 mb-8 shadow-lg">
    <div class="flex items-center gap-2 mb-6 border-b border-slate-700 pb-4">
        <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center text-white"><i class="fas fa-plus"></i></div>
        <h3 class="font-bold text-lg text-white">Add New Product</h3>
    </div>

    <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-5">
        
        <!-- Basic Info -->
        <div class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-400 mb-1">Product Name</label>
                <input type="text" name="name" required class="w-full bg-slate-900 border border-slate-600 p-2.5 rounded-lg text-white focus:border-blue-500 outline-none placeholder-slate-600">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-400 mb-1">Description</label>
                <textarea name="description" rows="2" class="w-full bg-slate-900 border border-slate-600 p-2.5 rounded-lg text-white text-sm focus:border-blue-500 outline-none placeholder-slate-500" placeholder="Product details..."></textarea>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Price (MMK)</label>
                    <input type="number" name="price" required class="w-full bg-slate-900 border border-slate-600 p-2.5 rounded-lg text-white focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-yellow-400 mb-1">Sale Price</label>
                    <input type="number" name="sale_price" placeholder="Optional" class="w-full bg-slate-900 border border-yellow-600/50 p-2.5 rounded-lg text-white focus:border-yellow-500 outline-none">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Category</label>
                    <select name="category_id" required class="w-full bg-slate-900 border border-slate-600 p-2.5 rounded-lg text-white focus:border-blue-500 outline-none">
                        <?php foreach($categories as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Region</label>
                    <select name="region_id" class="w-full bg-slate-900 border border-slate-600 p-2.5 rounded-lg text-white focus:border-blue-500 outline-none">
                        <option value="">Global / None</option>
                        <?php foreach($regions as $r): ?>
                            <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Right Col: Delivery & Image -->
        <div class="space-y-4">
            
            <!-- Image Upload -->
            <div class="bg-slate-700/30 p-4 rounded-lg border border-slate-600/50">
                <label class="block text-xs font-bold text-slate-400 mb-2">Cover Image</label>
                <div class="relative border-2 border-dashed border-slate-600 rounded-lg p-6 text-center hover:bg-slate-700/50 transition cursor-pointer">
                    <input type="file" name="image" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" onchange="document.getElementById('file-label').innerText = this.files[0].name">
                    <i class="fas fa-cloud-upload-alt text-2xl text-slate-500 mb-2"></i>
                    <p id="file-label" class="text-xs text-slate-400">Click to upload JPG/PNG</p>
                </div>
            </div>

            <!-- Delivery Logic -->
            <div class="bg-slate-700/30 p-4 rounded-lg border border-slate-600/50">
                <label class="block text-sm font-bold text-blue-400 mb-3">Delivery Configuration</label>
                <select id="delivery_type" name="delivery_type" onchange="toggleTypeFields()" class="w-full bg-slate-900 border border-slate-600 p-2.5 rounded-lg text-white mb-4 focus:border-blue-500 outline-none">
                    <option value="universal">Universal</option>
                    <option value="unique">Unique</option>
                    <option value="form">Form</option>
                </select>
                
                <div id="field_unique" class="hidden space-y-2">
                    <textarea name="unique_keys" rows="3" class="w-full bg-slate-900 border border-slate-600 p-2.5 rounded-lg text-white text-sm" placeholder="Keys (one per line)"></textarea>
                </div>
                <div id="field_universal" class="space-y-2">
                    <textarea name="universal_content" rows="3" class="w-full bg-slate-900 border border-slate-600 p-2.5 rounded-lg text-white text-sm" placeholder="Content"></textarea>
                </div>
                <div id="field_form" class="hidden space-y-2">
                    <input type="text" name="form_fields_raw" class="w-full bg-slate-900 border border-slate-600 p-2.5 rounded-lg text-white" placeholder="Fields csv">
                </div>
            </div>
        </div>
        
        <!-- Instructions -->
        <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-xs font-bold text-slate-400 mb-1">User Note</label>
                <textarea name="user_instruction" rows="2" class="w-full bg-slate-900 border border-slate-600 p-2.5 rounded-lg text-white text-sm" placeholder="Visible on product page"></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold text-red-400 mb-1">Mandatory Checkboxes</label>
                <textarea name="checkbox_instructions" rows="2" placeholder="One per line" class="w-full bg-slate-900 border border-slate-600 p-2.5 rounded-lg text-white text-sm"></textarea>
            </div>
        </div>

        <button type="submit" name="add_product" class="md:col-span-2 bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 rounded-lg shadow-lg transition flex justify-center items-center gap-2 transform active:scale-[0.99]">
            <i class="fas fa-save"></i> Create Product
        </button>
    </form>
</div>

<!-- Product Table -->
<div class="bg-slate-800 rounded-xl border border-slate-700 overflow-hidden shadow-lg">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-700/50 text-slate-400 uppercase text-xs">
            <tr>
                <th class="p-4">Image</th>
                <th class="p-4">Name</th>
                <th class="p-4">Stock</th>
                <th class="p-4 text-right">Price</th>
                <th class="p-4 text-right">Sale</th>
                <th class="p-4 text-right">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-700">
            <?php foreach($products as $p): ?>
                <tr class="hover:bg-slate-700/30 transition">
                    <td class="p-4">
                        <?php if($p['image_path']): ?>
                            <img src="<?php echo MAIN_SITE_URL . $p['image_path']; ?>" class="w-10 h-10 rounded object-cover bg-black">
                        <?php else: ?>
                            <div class="w-10 h-10 rounded bg-slate-700 flex items-center justify-center text-slate-500"><i class="fas fa-image"></i></div>
                        <?php endif; ?>
                    </td>
                    <td class="p-4">
                        <div class="font-bold text-white"><?php echo htmlspecialchars($p['name']); ?></div>
                        <div class="text-xs text-slate-500"><?php echo htmlspecialchars($p['cat_name']); ?></div>
                    </td>
                    <td class="p-4">
                        <?php if($p['delivery_type'] == 'unique'): ?>
                            <span class="font-mono <?php echo $p['stock_count']>0?'text-green-400':'text-red-400'; ?>"><?php echo $p['stock_count']; ?></span>
                        <?php else: ?>
                            <span class="text-slate-500">∞</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-4 text-right font-mono text-slate-300"><?php echo format_admin_currency($p['price']); ?></td>
                    <td class="p-4 text-right font-mono text-yellow-400 font-bold"><?php echo $p['sale_price'] ? format_admin_currency($p['sale_price']) : '-'; ?></td>
                    <td class="p-4 text-right">
                        <a href="<?php echo admin_url('product_edit', ['id' => $p['id']]); ?>" class="text-blue-400 hover:text-white mr-3 transition"><i class="fas fa-edit"></i></a>
                        <a href="<?php echo admin_url('products', ['delete' => $p['id']]); ?>" 
                           class="text-red-400 hover:text-white transition"
                           onclick="return confirm('Delete this product?')">
                           <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
    function toggleTypeFields() {
        const type = document.getElementById('delivery_type').value;
        ['unique', 'universal', 'form'].forEach(t => document.getElementById('field_' + t).classList.add('hidden'));
        document.getElementById('field_' + type).classList.remove('hidden');
    }
    toggleTypeFields();
</script>