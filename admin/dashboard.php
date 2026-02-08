<?php
// admin/dashboard.php

// Ensure DB connection exists
global $pdo;

// 1. Fetch Key Statistics
$pending_orders = get_pending_count($pdo);
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_reviews = $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn();

// Today's Revenue
$today_revenue = $pdo->query("SELECT SUM(total_price_paid) FROM orders WHERE status = 'active' AND DATE(created_at) = CURDATE()")->fetchColumn() ?: 0;

// 2. Fetch Chart Data (Last 7 Days Revenue)
$chart_labels = [];
$chart_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('M d', strtotime($date));
    $day_revenue = $pdo->query("SELECT SUM(total_price_paid) FROM orders WHERE status = 'active' AND DATE(created_at) = '$date'")->fetchColumn() ?: 0;
    $chart_data[] = $day_revenue;
}

// 3. Fetch Recent Orders
$orders = $pdo->query("
    SELECT o.*, u.username, p.name as product_name, p.delivery_type
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    JOIN products p ON o.product_id = p.id 
    ORDER BY o.created_at DESC LIMIT 5
")->fetchAll();

// 4. Fetch Recent Reviews
$recent_reviews = $pdo->query("
    SELECT r.*, u.username, p.name as product_name
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    JOIN products p ON r.product_id = p.id
    ORDER BY r.created_at DESC LIMIT 3
")->fetchAll();
?>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div>
        <h1 class="text-3xl font-bold text-white tracking-tight">Dashboard</h1>
        <p class="text-slate-400 text-sm mt-1">Store performance overview.</p>
    </div>
    <div class="flex gap-3">
        <a href="<?php echo admin_url('products'); ?>" class="bg-slate-800 border border-slate-600 hover:bg-slate-700 text-slate-200 px-4 py-2 rounded-lg font-medium text-sm transition flex items-center gap-2">
            <i class="fas fa-plus"></i> Add Product
        </a>
        <a href="<?php echo admin_url('reports'); ?>" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-lg font-bold text-sm shadow-lg transition flex items-center gap-2">
            <i class="fas fa-chart-line"></i> Reports
        </a>
    </div>
</div>

<!-- Top Stats Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    
    <!-- Pending Orders -->
    <div class="bg-slate-800 p-6 rounded-xl border border-slate-700/50 relative overflow-hidden group shadow-lg">
        <div class="absolute right-0 top-0 p-4 opacity-5 group-hover:opacity-10 transition"><i class="fas fa-clock text-6xl text-yellow-500"></i></div>
        <h3 class="text-slate-400 text-xs font-bold uppercase tracking-wider mb-2">Pending Orders</h3>
        <p class="text-3xl font-bold text-white"><?php echo number_format($pending_orders); ?></p>
        <a href="<?php echo admin_url('orders', ['status' => 'pending']); ?>" class="text-yellow-500 text-xs mt-4 inline-flex items-center hover:underline">
            <?php echo $pending_orders > 0 ? 'Action Required' : 'All Clear'; ?> <i class="fas fa-arrow-right ml-1"></i>
        </a>
    </div>

    <!-- Today's Revenue -->
    <div class="bg-slate-800 p-6 rounded-xl border border-slate-700/50 relative overflow-hidden group shadow-lg">
        <div class="absolute right-0 top-0 p-4 opacity-5 group-hover:opacity-10 transition"><i class="fas fa-wallet text-6xl text-green-500"></i></div>
        <h3 class="text-slate-400 text-xs font-bold uppercase tracking-wider mb-2">Today's Revenue</h3>
        <p class="text-3xl font-bold text-white"><?php echo format_admin_currency($today_revenue); ?></p>
        <p class="text-green-500 text-xs mt-4 flex items-center"><i class="fas fa-circle text-[8px] mr-2 animate-pulse"></i> Live Tracking</p>
    </div>

    <!-- Total Reviews -->
    <div class="bg-slate-800 p-6 rounded-xl border border-slate-700/50 relative overflow-hidden group shadow-lg">
        <div class="absolute right-0 top-0 p-4 opacity-5 group-hover:opacity-10 transition"><i class="fas fa-star text-6xl text-yellow-400"></i></div>
        <h3 class="text-slate-400 text-xs font-bold uppercase tracking-wider mb-2">Total Reviews</h3>
        <p class="text-3xl font-bold text-white"><?php echo number_format($total_reviews); ?></p>
        <a href="<?php echo admin_url('reviews'); ?>" class="text-yellow-400 text-xs mt-4 inline-flex items-center hover:underline">Moderate Reviews <i class="fas fa-arrow-right ml-1"></i></a>
    </div>

    <!-- Total Users -->
    <div class="bg-slate-800 p-6 rounded-xl border border-slate-700/50 relative overflow-hidden group shadow-lg">
        <div class="absolute right-0 top-0 p-4 opacity-5 group-hover:opacity-10 transition"><i class="fas fa-users text-6xl text-purple-500"></i></div>
        <h3 class="text-slate-400 text-xs font-bold uppercase tracking-wider mb-2">Total Users</h3>
        <p class="text-3xl font-bold text-white"><?php echo number_format($total_users); ?></p>
        <a href="<?php echo admin_url('users'); ?>" class="text-purple-400 text-xs mt-4 inline-flex items-center hover:underline">Manage Users <i class="fas fa-arrow-right ml-1"></i></a>
    </div>
</div>

<!-- Main Content Grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Sales Chart -->
    <div class="lg:col-span-2 bg-slate-800 rounded-xl border border-slate-700/50 p-6 shadow-xl">
        <h3 class="font-bold text-lg text-slate-200 mb-4">Revenue Overview (7 Days)</h3>
        <div class="relative h-64 w-full">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    <!-- Recent Reviews Widget -->
    <div class="lg:col-span-1 bg-slate-800 rounded-xl border border-slate-700/50 p-6 shadow-xl">
        <h3 class="font-bold text-lg text-slate-200 mb-4">Latest Reviews</h3>
        <div class="space-y-4">
            <?php if(empty($recent_reviews)): ?>
                <p class="text-sm text-slate-500 italic">No reviews yet.</p>
            <?php else: ?>
                <?php foreach($recent_reviews as $r): ?>
                    <div class="border-l-2 border-slate-600 pl-3">
                        <div class="flex justify-between items-start">
                            <span class="text-sm font-bold text-white"><?php echo htmlspecialchars($r['username']); ?></span>
                            <div class="flex text-[10px] text-yellow-500">
                                <?php for($i=1; $i<=5; $i++) echo ($i <= $r['rating']) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star text-slate-600"></i>'; ?>
                            </div>
                        </div>
                        <p class="text-xs text-slate-400 mt-1 line-clamp-2">"<?php echo htmlspecialchars($r['comment']); ?>"</p>
                        <p class="text-[10px] text-slate-500 mt-1"><?php echo htmlspecialchars($r['product_name']); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="mt-4 pt-4 border-t border-slate-700 text-center">
            <a href="<?php echo admin_url('reviews'); ?>" class="text-xs text-blue-400 hover:text-white transition">View All Reviews</a>
        </div>
    </div>

    <!-- Recent Orders Table -->
    <div class="lg:col-span-3 bg-slate-800 rounded-xl border border-slate-700/50 overflow-hidden shadow-xl">
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
                        <td class="p-4 font-medium text-white"><?php echo htmlspecialchars($o['username']); ?></td>
                        <td class="p-4 text-slate-300"><?php echo htmlspecialchars($o['product_name']); ?></td>
                        <td class="p-4 font-mono text-green-400 font-bold"><?php echo format_admin_currency($o['total_price_paid']); ?></td>
                        <td class="p-4"><?php echo format_status_badge($o['status']); ?></td>
                        <td class="p-4 pr-6 text-right">
                            <a href="<?php echo admin_url('order_detail', ['id' => $o['id']]); ?>" class="text-slate-400 hover:text-blue-400 transition font-medium text-xs border border-slate-600 hover:border-blue-400 px-3 py-1.5 rounded">
                                Manage
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Chart Config -->
<script>
    const ctx = document.getElementById('revenueChart').getContext('2d');
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(34, 197, 94, 0.2)'); // Green
    gradient.addColorStop(1, 'rgba(34, 197, 94, 0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                label: 'Revenue (Ks)',
                data: <?php echo json_encode($chart_data); ?>,
                borderColor: '#22c55e',
                backgroundColor: gradient,
                borderWidth: 2,
                tension: 0.4,
                pointBackgroundColor: '#fff',
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#334155' },
                    ticks: { color: '#94a3b8' }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#94a3b8' }
                }
            }
        }
    });
</script>