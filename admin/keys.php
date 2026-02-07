<?php
// admin/keys.php

// 1. Handle Delete Key
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Prevent deleting sold keys for audit reasons (Only delete is_sold = 0)
    $stmt = $pdo->prepare("DELETE FROM product_keys WHERE id = ? AND is_sold = 0");
    $stmt->execute([$id]);
    redirect(admin_url('keys', ['deleted' => 1]));
}

// 2. Filters Logic
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

// 3. Fetch Keys
$sql = "SELECT k.*, p.name as product_name 
        FROM product_keys k 
        JOIN products p ON k.product_id = p.id 
        WHERE $where_sql 
        ORDER BY k.is_sold ASC, k.id DESC 
        LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$keys = $stmt->fetchAll();

// 4. Products List for Filter Dropdown
$products = $pdo->query("SELECT id, name FROM products WHERE delivery_type = 'unique' ORDER BY name ASC")->fetchAll();
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-3xl font-bold text-white">Stock Management</h1>
        <p class="text-slate-400 text-sm mt-1">Audit and manage unique keys inventory.</p>
    </div>
</div>

<?php if(isset($_GET['deleted'])): ?>
    <div class="bg-green-500/20 text-green-400 p-4 rounded-xl border border-green-500/50 mb-6 flex items-center gap-3">
        <i class="fas fa-check-circle"></i> Unsold key deleted successfully.
    </div>
<?php endif; ?>

<!-- Filters -->
<div class="bg-slate-800 p-4 rounded-xl border border-slate-700 mb-6 shadow-lg">
    <form method="GET" class="flex flex-col md:flex-row gap-4 items-end">
        <input type="hidden" name="page" value="keys">
        
        <div class="flex-1 w-full">
            <label class="block text-xs font-bold text-slate-400 mb-1">Filter by Product</label>
            <select name="product_id" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm focus:border-blue-500 outline-none">
                <option value="0">All Products</option>
                <?php foreach($products as $p): ?>
                    <option value="<?php echo $p['id']; ?>" <?php echo $product_filter == $p['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($p['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="w-full md:w-48">
            <label class="block text-xs font-bold text-slate-400 mb-1">Status</label>
            <select name="status" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm focus:border-blue-500 outline-none">
                <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All</option>
                <option value="unsold" <?php echo $status_filter == 'unsold' ? 'selected' : ''; ?>>Available</option>
                <option value="sold" <?php echo $status_filter == 'sold' ? 'selected' : ''; ?>>Sold</option>
            </select>
        </div>

        <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white font-bold py-2 px-6 rounded-lg transition text-sm w-full md:w-auto">
            Apply Filters
        </button>
    </form>
</div>

<!-- Keys Table -->
<div class="bg-slate-800 rounded-xl border border-slate-700 overflow-hidden shadow-xl">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-900/50 text-slate-400 uppercase text-xs">
                <tr>
                    <th class="p-4 pl-6">Key Content</th>
                    <th class="p-4">Product</th>
                    <th class="p-4 text-center">Status</th>
                    <th class="p-4 text-center">Order ID</th>
                    <th class="p-4 text-right">Added Date</th>
                    <th class="p-4 text-center pr-6">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700">
                <?php foreach($keys as $k): ?>
                    <tr class="hover:bg-slate-700/30 transition group">
                        <td class="p-4 pl-6 font-mono text-slate-300 break-all max-w-xs text-xs">
                            <?php 
                                echo $k['is_sold'] ? substr($k['key_content'], 0, 8) . '•••••• (Hidden)' : htmlspecialchars($k['key_content']); 
                            ?>
                        </td>
                        <td class="p-4 font-medium text-white"><?php echo htmlspecialchars($k['product_name']); ?></td>
                        <td class="p-4 text-center">
                            <?php if($k['is_sold']): ?>
                                <span class="px-2 py-1 rounded text-[10px] font-bold bg-red-500/20 text-red-400 uppercase">Sold</span>
                            <?php else: ?>
                                <span class="px-2 py-1 rounded text-[10px] font-bold bg-green-500/20 text-green-400 uppercase">Available</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-4 text-center">
                            <?php if($k['order_id']): ?>
                                <a href="<?php echo admin_url('order_detail', ['id' => $k['order_id']]); ?>" class="text-blue-400 hover:underline font-mono">#<?php echo $k['order_id']; ?></a>
                            <?php else: ?>
                                <span class="text-slate-600">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-4 text-right text-slate-500 text-xs">
                            <?php echo date('M d, Y', strtotime($k['created_at'])); ?>
                        </td>
                        <td class="p-4 text-center pr-6">
                            <?php if(!$k['is_sold']): ?>
                                <a href="<?php echo admin_url('keys', ['delete' => $k['id']]); ?>" class="text-slate-500 hover:text-red-400 transition p-2 rounded hover:bg-slate-700" onclick="return confirm('Delete this key permanently?')" title="Delete Key">
                                    <i class="fas fa-trash"></i>
                                </a>
                            <?php else: ?>
                                <span class="text-slate-700 cursor-not-allowed" title="Cannot delete sold key"><i class="fas fa-trash"></i></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if(empty($keys)): ?>
                    <tr><td colspan="6" class="p-8 text-center text-slate-500 italic">No keys found matching your criteria.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="p-3 border-t border-slate-700 bg-slate-900/30 text-center text-xs text-slate-500">
        Showing last 100 entries. Use filters to find specific stock.
    </div>
</div>