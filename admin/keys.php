<?php
// admin/keys.php

// 1. Handle Add Keys (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_keys'])) {
    $product_id = (int)$_POST['product_id'];
    $keys_raw = $_POST['keys_input'];
    
    if ($product_id > 0 && !empty($keys_raw)) {
        $keys_array = explode("\n", $keys_raw);
        $added_count = 0;
        
        $stmt = $pdo->prepare("INSERT INTO product_keys (product_id, key_content) VALUES (?, ?)");
        
        foreach ($keys_array as $k) {
            $clean_key = trim($k);
            if (!empty($clean_key)) {
                $stmt->execute([$product_id, $clean_key]);
                $added_count++;
            }
        }
        
        if ($added_count > 0) {
            redirect(admin_url('keys', ['product_id' => $product_id, 'success_added' => $added_count]));
        }
    }
}

// 2. Handle Delete Key (GET)
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Only delete if unsold
    $stmt = $pdo->prepare("DELETE FROM product_keys WHERE id = ? AND is_sold = 0");
    $stmt->execute([$id]);
    redirect(admin_url('keys', ['deleted' => 1]));
}

// 3. Filters Logic
$product_filter = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

$where = ["p.delivery_type = 'unique'"];
$params = [];

if ($product_filter > 0) {
    $where[] = "k.product_id = ?";
    $params[] = $product_filter;
}

if ($status_filter === 'sold') {
    $where[] = "k.is_sold = 1";
} elseif ($status_filter === 'unsold') {
    $where[] = "k.is_sold = 0";
}

$where_sql = implode(' AND ', $where);

// 4. Fetch Keys
$sql = "SELECT k.*, p.name as product_name 
        FROM product_keys k 
        JOIN products p ON k.product_id = p.id 
        WHERE $where_sql 
        ORDER BY k.is_sold ASC, k.id DESC 
        LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$keys = $stmt->fetchAll();

// 5. Fetch Products List (For Dropdowns)
$products = $pdo->query("SELECT id, name FROM products WHERE delivery_type = 'unique' ORDER BY name ASC")->fetchAll();
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-3xl font-bold text-white">Stock Management</h1>
        <p class="text-slate-400 text-sm mt-1">Audit inventory and add new keys.</p>
    </div>
</div>

<!-- Success Messages -->
<?php if(isset($_GET['success_added'])): ?>
    <div class="bg-green-500/20 text-green-400 p-4 rounded-xl border border-green-500/50 mb-6 flex items-center gap-3">
        <i class="fas fa-plus-circle"></i> Successfully added <strong><?php echo (int)$_GET['success_added']; ?></strong> new keys.
    </div>
<?php endif; ?>

<?php if(isset($_GET['deleted'])): ?>
    <div class="bg-red-500/20 text-red-400 p-4 rounded-xl border border-red-500/50 mb-6 flex items-center gap-3">
        <i class="fas fa-trash-alt"></i> Key deleted.
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- ADD KEYS FORM -->
    <div class="lg:col-span-1">
        <div class="bg-slate-800 p-6 rounded-xl border border-slate-700 shadow-lg sticky top-6">
            <h3 class="font-bold text-white mb-4 border-b border-slate-700 pb-2 flex items-center gap-2">
                <i class="fas fa-plus text-blue-500"></i> Add Stock
            </h3>
            
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Select Product</label>
                    <select name="product_id" required class="w-full bg-slate-900 border border-slate-600 rounded-lg p-2.5 text-white text-sm focus:border-blue-500 outline-none">
                        <option value="">-- Choose Product --</option>
                        <?php foreach($products as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo $product_filter == $p['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Keys / Codes (One per line)</label>
                    <textarea name="keys_input" rows="6" required placeholder="XXXX-XXXX-XXXX&#10;YYYY-YYYY-YYYY" 
                              class="w-full bg-slate-900 border border-slate-600 rounded-lg p-3 text-white text-sm font-mono focus:border-blue-500 outline-none"></textarea>
                    <p class="text-[10px] text-slate-500 mt-1">Each line will be saved as a separate item.</p>
                </div>

                <button type="submit" name="add_keys" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-2.5 rounded-lg shadow-lg transition flex justify-center items-center gap-2">
                    <i class="fas fa-save"></i> Save Keys
                </button>
            </form>
        </div>
    </div>

    <!-- INVENTORY LIST -->
    <div class="lg:col-span-2">
        
        <!-- Filters -->
        <div class="bg-slate-800 p-4 rounded-xl border border-slate-700 mb-6 shadow-lg">
            <form method="GET" class="flex flex-col sm:flex-row gap-3 items-end">
                <input type="hidden" name="page" value="keys">
                
                <div class="flex-1 w-full">
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Filter View</label>
                    <select name="product_id" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm focus:border-blue-500 outline-none">
                        <option value="0">All Products</option>
                        <?php foreach($products as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo $product_filter == $p['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="w-full sm:w-40">
                    <select name="status" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm focus:border-blue-500 outline-none">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="unsold" <?php echo $status_filter == 'unsold' ? 'selected' : ''; ?>>Available</option>
                        <option value="sold" <?php echo $status_filter == 'sold' ? 'selected' : ''; ?>>Sold</option>
                    </select>
                </div>

                <button type="submit" class="bg-slate-700 hover:bg-slate-600 text-white font-medium py-2 px-4 rounded-lg transition text-sm">
                    Filter
                </button>
            </form>
        </div>

        <!-- Table -->
        <div class="bg-slate-800 rounded-xl border border-slate-700 overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-900/50 text-slate-400 uppercase text-xs">
                        <tr>
                            <th class="p-4 pl-6">Key Content</th>
                            <th class="p-4">Product</th>
                            <th class="p-4 text-center">Status</th>
                            <th class="p-4 text-center">Order</th>
                            <th class="p-4 text-right pr-6">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700">
                        <?php foreach($keys as $k): ?>
                            <tr class="hover:bg-slate-700/30 transition group">
                                <td class="p-4 pl-6 font-mono text-slate-300 break-all max-w-xs text-xs relative">
                                    <?php if($k['is_sold']): ?>
                                        <span class="opacity-50 line-through decoration-slate-500">
                                            <?php echo substr($k['key_content'], 0, 10) . '...'; ?>
                                        </span>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($k['key_content']); ?>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 font-medium text-white text-xs">
                                    <?php echo htmlspecialchars($k['product_name']); ?>
                                </td>
                                <td class="p-4 text-center">
                                    <?php if($k['is_sold']): ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-red-500/10 text-red-400 border border-red-500/20 uppercase">Sold</span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-green-500/10 text-green-400 border border-green-500/20 uppercase">Free</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 text-center">
                                    <?php if($k['order_id']): ?>
                                        <a href="<?php echo admin_url('order_detail', ['id' => $k['order_id']]); ?>" class="text-blue-400 hover:text-blue-300 hover:underline font-mono text-xs">#<?php echo $k['order_id']; ?></a>
                                    <?php else: ?>
                                        <span class="text-slate-600">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 text-right pr-6">
                                    <?php if(!$k['is_sold']): ?>
                                        <a href="<?php echo admin_url('keys', ['delete' => $k['id']]); ?>" 
                                           class="text-slate-500 hover:text-red-400 transition p-2 rounded hover:bg-slate-700" 
                                           onclick="return confirm('Delete this key permanently?')" 
                                           title="Delete Key">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-slate-700 cursor-not-allowed" title="Sold items cannot be deleted"><i class="fas fa-lock"></i></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if(empty($keys)): ?>
                            <tr><td colspan="5" class="p-8 text-center text-slate-500 italic">No keys found matching criteria.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="p-3 border-t border-slate-700 bg-slate-900/30 text-center text-xs text-slate-500">
                Showing recent 200 entries.
            </div>
        </div>
    </div>
</div>