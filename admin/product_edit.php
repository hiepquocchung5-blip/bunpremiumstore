<?php
// admin/product_edit.php

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 1. Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    try {
        $pdo->beginTransaction();

        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price = (float) $_POST['price'];
        $sale_price = !empty($_POST['sale_price']) ? (float) $_POST['sale_price'] : NULL;
        $cat_id = (int) $_POST['category_id'];
        $region_id = !empty($_POST['region_id']) ? $_POST['region_id'] : NULL;
        $instruction = trim($_POST['user_instruction']);
        $delivery_type = $_POST['delivery_type'];
        
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

        // Update Product Table
        $stmt = $pdo->prepare("
            UPDATE products SET 
            category_id = ?, region_id = ?, name = ?, description = ?, 
            price = ?, sale_price = ?, delivery_type = ?, 
            universal_content = ?, form_fields = ?, user_instruction = ?
            WHERE id = ?
        ");
        $stmt->execute([$cat_id, $region_id, $name, $description, $price, $sale_price, $delivery_type, $universal_content, $form_fields, $instruction, $id]);

        // Update Instructions: Delete old, Insert new
        $pdo->prepare("DELETE FROM product_instructions WHERE product_id = ?")->execute([$id]);
        
        if (!empty($_POST['checkbox_instructions'])) {
            $checkboxes = explode("\n", $_POST['checkbox_instructions']);
            $stmt_ins = $pdo->prepare("INSERT INTO product_instructions (product_id, instruction_text) VALUES (?, ?)");
            foreach ($checkboxes as $chk) {
                if (trim($chk)) $stmt_ins->execute([$id, trim($chk)]);
            }
        }

        $pdo->commit();
        redirect(admin_url('products', ['success' => 'updated']));

    } catch (Exception $e) {
        $pdo->rollBack();
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
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-white">Edit Product</h1>
        <a href="<?php echo admin_url('products'); ?>" class="text-slate-400 hover:text-white text-sm flex items-center gap-1"><i class="fas fa-arrow-left"></i> Back</a>
    </div>

    <?php if(isset($error)) echo "<div class='bg-red-500/20 text-red-400 p-4 rounded-xl border border-red-500/50 mb-6'>$error</div>"; ?>

    <div class="bg-slate-800 p-6 rounded-xl border border-slate-700 shadow-lg">
        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <!-- LEFT COLUMN: Basic Info -->
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Product Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required class="w-full bg-slate-900 border border-slate-600 p-2.5 rounded-lg text-white focus:border-blue-500 outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Description</label>
                    <textarea name="description" rows="3" class="w-full bg-slate-900 border border-slate-600 p-2.5 rounded-lg text-white text-sm focus:border-blue-500 outline-none"><?php echo htmlspecialchars($product['description']); ?></textarea>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Price (MMK)</label>
                        <input type="number" name="price" value="<?php echo $product['price']; ?>" required class="w-full bg-slate-900 border border-slate-600 p-2.5 rounded-lg text-white focus:border-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-yellow-400 mb-1">Sale Price</label>
                        <input type="number" name="sale_price" value="<?php echo $product['sale_price']; ?>" class="w-full bg-slate-900 border border-yellow-600/50 p-2.5 rounded-lg text-white focus:border-yellow-500 outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Category</label>
                        <select name="category_id" required class="w-full bg-slate-900 border border-slate-600 p-2.5 rounded-lg text-white focus:border-blue-500 outline-none">
                            <?php foreach($categories as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo $c['id'] == $product['category_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Region</label>
                        <select name="region_id" class="w-full bg-slate-900 border border-slate-600 p-2.5 rounded-lg text-white focus:border-blue-500 outline-none">
                            <option value="">Global / None</option>
                            <?php foreach($regions as $r): ?>
                                <option value="<?php echo $r['id']; ?>" <?php echo $r['id'] == $product['region_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($r['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Delivery & Rules -->
            <div class="space-y-4">
                <!-- Delivery Configuration -->
                <div class="bg-slate-700/30 p-4 rounded-lg border border-slate-600/50">
                    <label class="block text-sm font-bold text-blue-400 mb-3">Delivery Configuration</label>
                    
                    <select id="delivery_type" name="delivery_type" onchange="toggleTypeFields()" class="w-full bg-slate-900 border border-slate-600 p-2.5 rounded-lg text-white mb-4 focus:border-blue-500 outline-none">
                        <option value="universal" <?php echo $product['delivery_type'] == 'universal' ? 'selected' : ''; ?>>Universal</option>
                        <option value="unique" <?php echo $product['delivery_type'] == 'unique' ? 'selected' : ''; ?>>Unique</option>
                        <option value="form" <?php echo $product['delivery_type'] == 'form' ? 'selected' : ''; ?>>Form</option>
                    </select>

                    <!-- Type: Unique -->
                    <div id="field_unique" class="hidden">
                        <p class="text-xs text-yellow-500 bg-yellow-900/20 p-3 rounded border border-yellow-500/20 flex items-center gap-2">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>To manage unique keys, please use the <a href="<?php echo admin_url('keys', ['product_id' => $id]); ?>" class="underline hover:text-white font-bold">Stock Management</a> page.</span>
                        </p>
                    </div>

                    <!-- Type: Universal -->
                    <div id="field_universal" class="hidden space-y-2">
                        <label class="text-xs text-green-400 font-bold block">Content to Deliver</label>
                        <textarea name="universal_content" rows="4" class="w-full bg-slate-900 border border-slate-600 p-2.5 rounded-lg text-white text-sm font-mono"><?php echo htmlspecialchars($product['universal_content']); ?></textarea>
                    </div>

                    <!-- Type: Form -->
                    <div id="field_form" class="hidden space-y-2">
                        <label class="text-xs text-blue-400 font-bold block">Required User Fields (Comma separated)</label>
                        <input type="text" name="form_fields_raw" value="<?php echo htmlspecialchars($current_form_fields); ?>" class="w-full bg-slate-900 border border-slate-600 p-2.5 rounded-lg text-white">
                    </div>
                </div>
                
                <!-- Instructions -->
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">User Note (Product Page)</label>
                        <textarea name="user_instruction" rows="2" class="w-full bg-slate-900 border border-slate-600 p-2.5 rounded-lg text-white text-sm"><?php echo htmlspecialchars($product['user_instruction']); ?></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-red-400 mb-1">Mandatory Checkboxes (One per line)</label>
                        <textarea name="checkbox_instructions" rows="2" class="w-full bg-slate-900 border border-slate-600 p-2.5 rounded-lg text-white text-sm"><?php echo htmlspecialchars($current_instructions); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Footer Action -->
            <div class="md:col-span-2 pt-4 border-t border-slate-700">
                <button type="submit" name="update_product" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 rounded-lg shadow-lg transition flex justify-center items-center gap-2">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleTypeFields() {
        const type = document.getElementById('delivery_type').value;
        ['unique', 'universal', 'form'].forEach(t => document.getElementById('field_' + t).classList.add('hidden'));
        document.getElementById('field_' + type).classList.remove('hidden');
    }
    toggleTypeFields();
</script>