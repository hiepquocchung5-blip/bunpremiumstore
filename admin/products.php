<?php
// admin/products.php

// 1. Handle POST: Add Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    try {
        $pdo->beginTransaction();

        $name = trim($_POST['name']);
        $price = (float) $_POST['price'];
        $cat_id = (int) $_POST['category_id'];
        $region_id = !empty($_POST['region_id']) ? $_POST['region_id'] : NULL;
        $delivery_type = $_POST['delivery_type'];
        $instruction = trim($_POST['user_instruction']);
        
        // Handle Delivery Specific Data
        $universal_content = ($delivery_type === 'universal') ? trim($_POST['universal_content']) : NULL;
        
        // Handle Form Fields Schema (Convert comma-separated list to JSON)
        $form_fields = NULL;
        if ($delivery_type === 'form' && !empty($_POST['form_fields_raw'])) {
            $fields = explode(',', $_POST['form_fields_raw']);
            $form_schema = [];
            foreach($fields as $f) {
                if(trim($f)) $form_schema[] = ['label' => trim($f), 'type' => 'text'];
            }
            $form_fields = !empty($form_schema) ? json_encode($form_schema) : NULL;
        }

        // Insert Product
        $stmt = $pdo->prepare("INSERT INTO products (category_id, region_id, name, price, delivery_type, universal_content, form_fields, user_instruction) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$cat_id, $region_id, $name, $price, $delivery_type, $universal_content, $form_fields, $instruction]);
        $product_id = $pdo->lastInsertId();

        // Handle Unique Keys (Bulk Insert - One per line)
        if ($delivery_type === 'unique' && !empty($_POST['unique_keys'])) {
            $keys = explode("\n", $_POST['unique_keys']);
            $stmt_key = $pdo->prepare("INSERT INTO product_keys (product_id, key_content) VALUES (?, ?)");
            foreach ($keys as $k) {
                if (trim($k)) $stmt_key->execute([$product_id, trim($k)]);
            }
        }

        // Handle Mandatory Checkboxes (Bulk Insert - One per line)
        if (!empty($_POST['checkbox_instructions'])) {
            $checkboxes = explode("\n", $_POST['checkbox_instructions']);
            $stmt_ins = $pdo->prepare("INSERT INTO product_instructions (product_id, instruction_text) VALUES (?, ?)");
            foreach ($checkboxes as $chk) {
                if (trim($chk)) $stmt_ins->execute([$product_id, trim($chk)]);
            }
        }

        $pdo->commit();
        
        // Redirect using helper function
        redirect(admin_url('products', ['success' => 1]));

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error adding product: " . $e->getMessage();
    }
}

// 2. Handle GET: Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Note: Database foreign keys should handle cascading deletes for keys/instructions
    $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
    redirect(admin_url('products', ['deleted' => 1]));
}

// 3. Fetch Data for View
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
        <p class="text-slate-400 text-sm mt-1">Manage your inventory and delivery settings.</p>
    </div>
</div>

<?php if(isset($_GET['success'])): ?>
    <div class="bg-green-500/20 text-green-400 p-4 rounded-xl border border-green-500/50 mb-6 flex items-center gap-3">
        <i class="fas fa-check-circle"></i> Product created successfully.
    </div>
<?php endif; ?>

<?php if(isset($error)): ?>
    <div class="bg-red-500/20 text-red-400 p-4 rounded-xl border border-red-500/50 mb-6">
        <?php echo $error; ?>
    </div>
<?php endif; ?>

<!-- Add Product Form -->
<div class="bg-slate-800 p-6 rounded-xl border border-slate-700 mb-8 shadow-lg">
    <div class="flex items-center gap-2 mb-6 border-b border-slate-700 pb-4">
        <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center text-white"><i class="fas fa-plus"></i></div>
        <h3 class="font-bold text-lg text-white">Add New Product</h3>
    </div>

    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-5">
        
        <!-- Basic Info -->
        <div class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-400 mb-1">Product Name</label>
                <input type="text" name="name" required class="w-full bg-slate-900 border border-slate-600 p-2.5 rounded-lg text-white focus:border-blue-500 outline-none placeholder-slate-600">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Price (MMK)</label>
                    <input type="number" name="price" required class="w-full bg-slate-900 border border-slate-600 p-2.5 rounded-lg text-white focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Category</label>
                    <select name="category_id" required class="w-full bg-slate-900 border border-slate-600 p-2.5 rounded-lg text-white focus:border-blue-500 outline-none">
                        <option value="">Select...</option>
                        <?php foreach($categories as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-400 mb-1">Region (Optional)</label>
                <select name="region_id" class="w-full bg-slate-900 border border-slate-600 p-2.5 rounded-lg text-white focus:border-blue-500 outline-none">
                    <option value="">Global / None</option>
                    <?php foreach($regions as $r): ?>
                        <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Delivery Logic -->
        <div class="bg-slate-700/30 p-4 rounded-lg border border-slate-600/50">
            <label class="block text-sm font-bold text-blue-400 mb-3">Delivery Configuration</label>
            
            <select id="delivery_type" name="delivery_type" onchange="toggleTypeFields()" class="w-full bg-slate-900 border border-slate-600 p-2.5 rounded-lg text-white mb-4 focus:border-blue-500 outline-none">
                <option value="universal">Universal (Infinite Stock / E-book / Shared Account)</option>
                <option value="unique">Unique (Keys / PINs / Private Accounts)</option>
                <option value="form">Form (Top-up / Service / User Input)</option>
            </select>

            <!-- Type: Unique -->
            <div id="field_unique" class="hidden space-y-2">
                <label class="text-xs text-yellow-500 font-bold block">Upload Keys (One per line)</label>
                <textarea name="unique_keys" rows="4" placeholder="AAAA-BBBB-CCCC&#10;1111-2222-3333" class="w-full bg-slate-900 border border-slate-600 p-2.5 rounded-lg text-white font-mono text-sm"></textarea>
                <p class="text-[10px] text-slate-500">Each line counts as 1 stock item.</p>
            </div>

            <!-- Type: Universal -->
            <div id="field_universal" class="space-y-2">
                <label class="text-xs text-green-400 font-bold block">Content to Deliver</label>
                <textarea name="universal_content" rows="4" placeholder="Download link or shared credentials..." class="w-full bg-slate-900 border border-slate-600 p-2.5 rounded-lg text-white text-sm"></textarea>
                <p class="text-[10px] text-slate-500">This exact text is sent to every buyer.</p>
            </div>

            <!-- Type: Form -->
            <div id="field_form" class="hidden space-y-2">
                <label class="text-xs text-blue-400 font-bold block">Required User Fields (Comma separated)</label>
                <input type="text" name="form_fields_raw" placeholder="Player ID, Server Name, BattleTag" class="w-full bg-slate-900 border border-slate-600 p-2.5 rounded-lg text-white">
                <p class="text-[10px] text-slate-500">User must fill these fields during checkout.</p>
            </div>
        </div>
        
        <!-- Instructions & Terms -->
        <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-xs font-bold text-slate-400 mb-1">User Note (Product Page)</label>
                <textarea name="user_instruction" rows="3" class="w-full bg-slate-900 border border-slate-600 p-2.5 rounded-lg text-white text-sm" placeholder="e.g. Read instructions carefully before buying."></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold text-red-400 mb-1">Mandatory Checkboxes (One per line)</label>
                <textarea name="checkbox_instructions" rows="3" placeholder="I agree to Terms&#10;No Refunds&#10;I will not change password" class="w-full bg-slate-900 border border-slate-600 p-2.5 rounded-lg text-white text-sm"></textarea>
            </div>
        </div>

        <button type="submit" name="add_product" class="md:col-span-2 bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 rounded-lg shadow-lg transition flex justify-center items-center gap-2">
            <i class="fas fa-save"></i> Create Product
        </button>
    </form>
</div>

<!-- Product Table -->
<div class="bg-slate-800 rounded-xl border border-slate-700 overflow-hidden shadow-lg">
    <div class="p-4 border-b border-slate-700 font-bold text-sm text-slate-300">Inventory List</div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-700/50 text-slate-400 uppercase text-xs">
                <tr>
                    <th class="p-4">Name</th>
                    <th class="p-4">Category</th>
                    <th class="p-4">Type</th>
                    <th class="p-4">Stock</th>
                    <th class="p-4 text-right">Price</th>
                    <th class="p-4 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700">
                <?php foreach($products as $p): ?>
                    <tr class="hover:bg-slate-700/30 transition group">
                        <td class="p-4 font-bold text-white"><?php echo htmlspecialchars($p['name']); ?></td>
                        <td class="p-4 text-slate-400"><?php echo htmlspecialchars($p['cat_name']); ?></td>
                        <td class="p-4">
                            <span class="px-2 py-1 rounded text-xs font-bold uppercase 
                                <?php echo $p['delivery_type'] == 'unique' ? 'bg-yellow-500/20 text-yellow-400' : ($p['delivery_type'] == 'form' ? 'bg-blue-500/20 text-blue-400' : 'bg-green-500/20 text-green-400'); ?>">
                                <?php echo $p['delivery_type']; ?>
                            </span>
                        </td>
                        <td class="p-4">
                            <?php if($p['delivery_type'] == 'unique'): ?>
                                <span class="font-mono px-2 py-1 rounded <?php echo $p['stock_count'] > 0 ? 'bg-slate-900 text-green-400' : 'bg-red-900/30 text-red-400'; ?>">
                                    <?php echo $p['stock_count']; ?>
                                </span>
                            <?php else: ?>
                                <span class="text-slate-500 text-lg">∞</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-4 text-right font-mono text-green-400"><?php echo format_admin_currency($p['price']); ?></td>
                        <td class="p-4 text-right">
                            <a href="<?php echo admin_url('products', ['delete' => $p['id']]); ?>" 
                               class="text-slate-500 hover:text-red-400 transition"
                               onclick="return confirm('Delete this product? Orders linked to it will remain in history.')">
                               <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function toggleTypeFields() {
        const type = document.getElementById('delivery_type').value;
        const types = ['unique', 'universal', 'form'];
        
        types.forEach(t => {
            document.getElementById('field_' + t).classList.add('hidden');
        });

        const activeField = document.getElementById('field_' + type);
        if (activeField) {
            activeField.classList.remove('hidden');
        }
    }
    // Initialize on load
    toggleTypeFields();
</script>