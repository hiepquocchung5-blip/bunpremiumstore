<?php
// admin/dashboard.php

// Ensure DB connection exists (handled by index->header)
global $pdo;

// 1. Fetch Key Statistics
$pending_orders = get_pending_count($pdo);
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// Today's Revenue
$today_revenue = $pdo->query("SELECT SUM(total_price_paid) FROM orders WHERE status = 'active' AND DATE(created_at) = CURDATE()")->fetchColumn() ?: 0;

// Monthly Financials (Revenue vs Expenses)
$current_month = date('m');
$monthly_revenue = $pdo->query("SELECT SUM(total_price_paid) FROM orders WHERE status = 'active' AND MONTH(created_at) = $current_month")->fetchColumn() ?: 0;
$monthly_expenses = $pdo->query("SELECT SUM(amount) FROM expenses WHERE MONTH(created_at) = $current_month")->fetchColumn() ?: 0;
$monthly_profit = $monthly_revenue - $monthly_expenses;

// 2. Fetch Recent Orders
$orders = $pdo->query("
    SELECT o.*, u.username, p.name as product_name, p.delivery_type
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    JOIN products p ON o.product_id = p.id 
    ORDER BY o.created_at DESC LIMIT 5
")->fetchAll();
?>

<div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div>
        <h1 class="text-3xl font-bold text-white tracking-tight">Dashboard</h1>
        <p class="text-slate-400 text-sm mt-1">Overview of store performance for <?php echo date('F Y'); ?>.</p>
    </div>
    <div class="flex gap-3">
        <a href="<?php echo admin_url('products'); ?>" class="bg-slate-800 border border-slate-600 hover:bg-slate-700 text-slate-200 px-4 py-2 rounded-lg font-medium text-sm transition flex items-center gap-2">
            <i class="fas fa-plus-circle"></i> Add Product
        </a>
        <a href="<?php echo admin_url('reports'); ?>" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-lg font-bold text-sm shadow-lg transition flex items-center gap-2">
            <i class="fas fa-chart-line"></i> Financial Report
        </a>
    </div>
</div>

<!-- Stats Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    
    <!-- Pending Orders -->
    <div class="bg-slate-800 p-6 rounded-xl border border-slate-700/50 relative overflow-hidden group hover:border-yellow-500/30 transition shadow-lg">
        <div class="absolute right-0 top-0 p-4 opacity-5 group-hover:scale-110 transition duration-500"><i class="fas fa-clock text-6xl text-yellow-500"></i></div>
        <h3 class="text-slate-400 text-xs font-bold uppercase tracking-wider mb-2">Pending Orders</h3>
        <p class="text-3xl font-bold text-white"><?php echo number_format($pending_orders); ?></p>
        <a href="<?php echo admin_url('orders', ['status' => 'pending']); ?>" class="text-yellow-500 text-xs mt-4 inline-flex items-center hover:underline">
            <?php echo $pending_orders > 0 ? 'Action Required' : 'All Clear'; ?> <i class="fas fa-arrow-right ml-1"></i>
        </a>
    </div>

    <!-- Today's Revenue -->
    <div class="bg-slate-800 p-6 rounded-xl border border-slate-700/50 relative overflow-hidden group hover:border-green-500/30 transition shadow-lg">
        <div class="absolute right-0 top-0 p-4 opacity-5 group-hover:scale-110 transition duration-500"><i class="fas fa-wallet text-6xl text-green-500"></i></div>
        <h3 class="text-slate-400 text-xs font-bold uppercase tracking-wider mb-2">Today's Revenue</h3>
        <p class="text-3xl font-bold text-white"><?php echo format_admin_currency($today_revenue); ?></p>
        <p class="text-green-500 text-xs mt-4 flex items-center"><i class="fas fa-circle text-[8px] mr-2 animate-pulse"></i> Live Tracking</p>
    </div>

    <!-- Monthly Profit -->
    <div class="bg-slate-800 p-6 rounded-xl border border-slate-700/50 relative overflow-hidden group hover:border-blue-500/30 transition shadow-lg">
        <div class="absolute right-0 top-0 p-4 opacity-5 group-hover:scale-110 transition duration-500"><i class="fas fa-chart-pie text-6xl text-blue-500"></i></div>
        <h3 class="text-slate-400 text-xs font-bold uppercase tracking-wider mb-2">Net Profit (<?php echo date('M'); ?>)</h3>
        <p class="text-3xl font-bold <?php echo $monthly_profit >= 0 ? 'text-blue-400' : 'text-red-400'; ?>">
            <?php echo format_admin_currency($monthly_profit); ?>
        </p>
        <p class="text-slate-500 text-xs mt-4">Exp: <?php echo format_admin_currency($monthly_expenses); ?></p>
    </div>

    <!-- Total Users -->
    <div class="bg-slate-800 p-6 rounded-xl border border-slate-700/50 relative overflow-hidden group hover:border-purple-500/30 transition shadow-lg">
        <div class="absolute right-0 top-0 p-4 opacity-5 group-hover:scale-110 transition duration-500"><i class="fas fa-users text-6xl text-purple-500"></i></div>
        <h3 class="text-slate-400 text-xs font-bold uppercase tracking-wider mb-2">Total Users</h3>
        <p class="text-3xl font-bold text-white"><?php echo number_format($total_users); ?></p>
        <a href="<?php echo admin_url('users'); ?>" class="text-purple-400 text-xs mt-4 inline-flex items-center hover:underline">Manage Users <i class="fas fa-arrow-right ml-1"></i></a>
    </div>
</div>

<!-- Recent Activity & Shortcuts -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Recent Orders Table -->
    <div class="lg:col-span-2 bg-slate-800 rounded-xl border border-slate-700/50 overflow-hidden shadow-xl">
        <div class="p-6 border-b border-slate-700/50 flex justify-between items-center">
            <h3 class="font-bold text-lg text-slate-200">Recent Orders</h3>
            <a href="<?php echo admin_url('orders'); ?>" class="text-xs text-blue-400 hover:text-blue-300 transition uppercase font-bold tracking-wide">View All</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-700/30 text-slate-400 uppercase text-xs font-semibold">
                    <tr>
                        <th class="p-4 pl-6">ID</th>
                        <th class="p-4">Customer</th>
                        <th class="p-4">Product</th>
                        <th class="p-4">Amount</th>
                        <th class="p-4">Status</th>
                        <th class="p-4 pr-6 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/50">
                    <?php foreach($orders as $o): ?>
                    <tr class="hover:bg-slate-700/20 transition group">
                        <td class="p-4 pl-6 text-slate-500 font-mono">#<?php echo $o['id']; ?></td>
                        <td class="p-4 font-medium text-white">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full bg-slate-700 flex items-center justify-center text-[10px] text-slate-300 font-bold border border-slate-600">
                                    <?php echo strtoupper(substr($o['username'], 0, 1)); ?>
                                </div>
                                <?php echo htmlspecialchars($o['username']); ?>
                            </div>
                        </td>
                        <td class="p-4">
                            <div class="text-slate-300"><?php echo htmlspecialchars($o['product_name']); ?></div>
                            <div class="text-[10px] text-slate-500 uppercase tracking-wider bg-slate-700/50 px-1 rounded inline-block mt-1"><?php echo $o['delivery_type']; ?></div>
                        </td>
                        <td class="p-4 font-mono text-green-400 font-bold"><?php echo format_admin_currency($o['total_price_paid']); ?></td>
                        <td class="p-4"><?php echo format_status_badge($o['status']); ?></td>
                        <td class="p-4 pr-6 text-right">
                            <a href="<?php echo admin_url('order_detail', ['id' => $o['id']]); ?>" class="text-slate-400 hover:text-blue-400 transition font-medium text-xs border border-slate-600 hover:border-blue-400 px-3 py-1.5 rounded">
                                Manage
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($orders)): ?>
                        <tr><td colspan="6" class="p-8 text-center text-slate-500 italic">No recent orders found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Quick Actions Panel -->
    <div class="lg:col-span-1 space-y-6">
        
        <!-- System Status -->
        <div class="bg-slate-800 rounded-xl border border-slate-700/50 p-6 shadow-lg">
            <h3 class="font-bold text-white mb-4">System Status</h3>
            <div class="space-y-4">
                <div class="flex justify-between items-center border-b border-slate-700 pb-2">
                    <span class="text-sm text-slate-400">Database</span>
                    <span class="text-xs font-bold text-green-400 bg-green-900/30 px-2 py-1 rounded border border-green-500/20">Connected</span>
                </div>
                <div class="flex justify-between items-center border-b border-slate-700 pb-2">
                    <span class="text-sm text-slate-400">Telegram Bot</span>
                    <span class="text-xs font-bold text-blue-400 bg-blue-900/30 px-2 py-1 rounded border border-blue-500/20">Active</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-slate-400">Exchange Rate</span>
                    <span class="text-xs font-bold text-slate-300 bg-slate-700 px-2 py-1 rounded font-mono">1 USD = <?php echo number_format(EXCHANGE_RATE); ?> Ks</span>
                </div>
            </div>
        </div>

        <!-- Quick Management Links -->
        <div class="bg-gradient-to-br from-blue-900/40 to-slate-800 rounded-xl border border-blue-500/20 p-6 shadow-lg">
            <h3 class="font-bold text-white mb-4">Quick Management</h3>
            <div class="grid grid-cols-2 gap-3">
                <a href="<?php echo admin_url('banners'); ?>" class="bg-slate-700/50 hover:bg-blue-600/20 hover:border-blue-500/50 border border-transparent p-3 rounded-lg text-center transition group">
                    <i class="fas fa-images text-2xl text-blue-400 mb-2 group-hover:scale-110 transition"></i>
                    <p class="text-xs font-medium text-slate-300">Banners</p>
                </a>
                <a href="<?php echo admin_url('products'); ?>" class="bg-slate-700/50 hover:bg-green-600/20 hover:border-green-500/50 border border-transparent p-3 rounded-lg text-center transition group">
                    <i class="fas fa-tags text-2xl text-green-400 mb-2 group-hover:scale-110 transition"></i>
                    <p class="text-xs font-medium text-slate-300">Products</p>
                </a>
                <a href="<?php echo admin_url('users'); ?>" class="bg-slate-700/50 hover:bg-purple-600/20 hover:border-purple-500/50 border border-transparent p-3 rounded-lg text-center transition group">
                    <i class="fas fa-users text-2xl text-purple-400 mb-2 group-hover:scale-110 transition"></i>
                    <p class="text-xs font-medium text-slate-300">Customers</p>
                </a>
                <a href="<?php echo admin_url('reports'); ?>" class="bg-slate-700/50 hover:bg-yellow-600/20 hover:border-yellow-500/50 border border-transparent p-3 rounded-lg text-center transition group">
                    <i class="fas fa-calculator text-2xl text-yellow-400 mb-2 group-hover:scale-110 transition"></i>
                    <p class="text-xs font-medium text-slate-300">Expenses</p>
                </a>
            </div>
        </div>

    </div>
</div>