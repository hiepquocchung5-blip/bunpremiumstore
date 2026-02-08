<?php
// admin/orders.php

// 1. Filter Logic
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';

$where = [];
$params = [];

// Status Filter
if ($status_filter !== 'all') {
    $where[] = "o.status = ?";
    $params[] = $status_filter;
}

// Search Filter
if ($search_query) {
    $where[] = "(o.id LIKE ? OR u.username LIKE ? OR o.transaction_last_6 LIKE ?)";
    $term = "%$search_query%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$where_sql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// 2. Fetch Orders
$sql = "SELECT o.*, u.username, p.name as product_name 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        JOIN products p ON o.product_id = p.id 
        $where_sql
        ORDER BY o.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
    <div>
        <h1 class="text-3xl font-bold text-white">Order Management</h1>
        <p class="text-slate-400 text-sm mt-1">View and process customer orders.</p>
    </div>
    
    <!-- Filters & Search -->
    <form method="GET" class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
        <input type="hidden" name="page" value="orders">
        
        <!-- Search Input -->
        <div class="relative">
            <i class="fas fa-search absolute left-3 top-3 text-slate-500 text-xs"></i>
            <input type="text" name="q" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Order ID, User, Txn..." 
                   class="bg-slate-800 border border-slate-600 rounded-lg pl-9 pr-4 py-2 text-sm text-white focus:border-blue-500 outline-none w-full sm:w-64">
        </div>

        <!-- Status Filter -->
        <div class="flex bg-slate-800 rounded-lg p-1 border border-slate-700">
            <button name="status" value="all" class="px-3 py-1.5 rounded text-xs font-medium transition <?php echo $status_filter == 'all' ? 'bg-slate-600 text-white' : 'text-slate-400 hover:text-white'; ?>">All</button>
            <button name="status" value="pending" class="px-3 py-1.5 rounded text-xs font-medium transition <?php echo $status_filter == 'pending' ? 'bg-yellow-600 text-white' : 'text-slate-400 hover:text-white'; ?>">Pending</button>
            <button name="status" value="active" class="px-3 py-1.5 rounded text-xs font-medium transition <?php echo $status_filter == 'active' ? 'bg-green-600 text-white' : 'text-slate-400 hover:text-white'; ?>">Active</button>
        </div>
    </form>
</div>

<div class="bg-slate-800 rounded-xl border border-slate-700 overflow-hidden shadow-xl">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-900/50 text-slate-400 uppercase text-xs">
                <tr>
                    <th class="p-4 pl-6">Order ID</th>
                    <th class="p-4">Customer</th>
                    <th class="p-4">Product</th>
                    <th class="p-4">Txn ID</th>
                    <th class="p-4">Amount</th>
                    <th class="p-4">Status</th>
                    <th class="p-4 text-right pr-6">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/50">
                <?php foreach($orders as $o): ?>
                    <tr class="hover:bg-slate-700/30 transition group">
                        <td class="p-4 pl-6 text-slate-500 font-mono">#<?php echo $o['id']; ?></td>
                        <td class="p-4 font-medium text-white"><?php echo htmlspecialchars($o['username']); ?></td>
                        <td class="p-4 text-slate-300"><?php echo htmlspecialchars($o['product_name']); ?></td>
                        <td class="p-4 font-mono text-yellow-500 select-all"><?php echo $o['transaction_last_6']; ?></td>
                        <td class="p-4 font-mono text-green-400"><?php echo format_admin_currency($o['total_price_paid']); ?></td>
                        <td class="p-4"><?php echo format_status_badge($o['status']); ?></td>
                        <td class="p-4 text-right pr-6">
                            <a href="<?php echo admin_url('order_detail', ['id' => $o['id']]); ?>" 
                               class="bg-blue-600/20 text-blue-400 hover:bg-blue-600 hover:text-white px-3 py-1.5 rounded text-xs font-bold transition border border-blue-600/30">
                                Manage
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                
                <?php if(empty($orders)): ?>
                    <tr>
                        <td colspan="7" class="p-10 text-center text-slate-500">
                            <i class="fas fa-box-open text-4xl mb-3 opacity-50"></i>
                            <p>No orders found matching your search.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>