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

// 5. Query unique delivery products with stock count < 3
$low_stock_items = $pdo->query("
    SELECT p.id, p.name, COUNT(pk.id) as stock 
    FROM products p 
    LEFT JOIN product_keys pk ON pk.product_id = p.id AND pk.is_sold = 0 
    WHERE p.delivery_type = 'unique' 
    GROUP BY p.id 
    HAVING stock < 3
")->fetchAll();
$low_stock_count = count($low_stock_items);
?>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="mb-12 flex flex-col md:flex-row justify-between items-start md:items-center gap-8">
    <div>
        <h1 class="text-3xl md:text-5xl font-extrabold text-white tracking-tight font-heading">Dashboard <span class="text-indigo-500">.</span></h1>
        <p class="text-slate-500 text-sm mt-3 max-w-md leading-relaxed">System overview and real-time performance metrics for your digital marketplace.</p>
    </div>
    <div class="flex flex-wrap gap-4">
        <a href="<?php echo admin_url('products'); ?>" class="group bg-slate-800/40 hover:bg-slate-700/60 border border-white/5 text-white px-6 py-3 rounded-2xl font-bold text-sm transition-all flex items-center gap-3 backdrop-blur-sm relative">
            <div class="w-8 h-8 rounded-lg bg-indigo-500/10 flex items-center justify-center text-indigo-400 group-hover:scale-110 transition-transform">
                <i class="fas fa-plus"></i>
            </div>
            <span>Catalog Item</span>
            <?php if ($low_stock_count > 0): ?>
                <span class="absolute -top-1.5 -right-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-rose-500 text-[10px] font-bold text-white animate-pulse shadow-lg shadow-rose-500/30">
                    <?php echo $low_stock_count; ?>
                </span>
            <?php endif; ?>
        </a>
        <a href="<?php echo admin_url('reports'); ?>" class="bg-indigo-600 hover:bg-indigo-500 text-white px-8 py-3 rounded-2xl font-bold text-sm shadow-xl shadow-indigo-600/20 transition-all flex items-center gap-3 active:scale-95">
            <i class="fas fa-chart-line"></i>
            <span>Executive Report</span>
        </a>
    </div>
</div>

<!-- Low Stock Warning Widget -->
<?php if ($low_stock_count > 0): ?>
<div class="mb-12 bg-rose-500/10 border border-rose-500/20 rounded-[2rem] p-6 flex flex-col md:flex-row items-center justify-between gap-6">
    <div class="flex items-center gap-5">
        <div class="w-14 h-14 rounded-2xl bg-rose-500/20 flex items-center justify-center text-rose-400 text-2xl shrink-0 animate-pulse">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div>
            <h3 class="text-lg font-bold text-white leading-tight">Low Stock Alert!</h3>
            <p class="text-rose-400/80 text-xs mt-1 font-medium">Some unique key items are running low on keys in the warehouse.</p>
        </div>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <?php foreach ($low_stock_items as $item): ?>
            <span class="bg-rose-500/20 border border-rose-500/30 text-rose-300 text-[10px] font-bold px-3 py-1.5 rounded-xl uppercase tracking-wider">
                <?php echo htmlspecialchars($item['name']); ?> (<?php echo $item['stock']; ?> left)
            </span>
        <?php endforeach; ?>
        <a href="<?php echo admin_url('products'); ?>" class="bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs px-6 py-2.5 rounded-xl transition-all shadow-lg active:scale-95">
            Restock
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Top Stats Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-12">
    
    <!-- Pending Orders -->
    <div class="custom-card p-8 group hover:border-amber-500/40 relative overflow-hidden">
        <div class="absolute top-0 right-0 p-4">
            <div class="w-14 h-14 rounded-2xl bg-amber-500/10 flex items-center justify-center text-amber-500 text-2xl group-hover:rotate-6 transition-all duration-300">
                <i class="fas fa-clock"></i>
            </div>
        </div>
        <p class="text-[11px] text-slate-500 font-bold uppercase tracking-[0.2em] mb-4">Pending Requests</p>
        <h3 class="text-4xl font-bold text-white mb-6 font-heading"><?php echo number_format($pending_orders); ?></h3>
        <a href="<?php echo admin_url('orders', ['status' => 'pending']); ?>" class="text-amber-500 text-xs font-bold inline-flex items-center gap-2 group/btn">
            <span>Needs Attention</span>
            <i class="fas fa-arrow-right group-hover/btn:translate-x-1 transition-transform"></i>
        </a>
    </div>

    <!-- Today's Revenue -->
    <div class="custom-card p-8 group hover:border-emerald-500/40 relative overflow-hidden">
        <div class="absolute top-0 right-0 p-4">
            <div class="w-14 h-14 rounded-2xl bg-emerald-500/10 flex items-center justify-center text-emerald-500 text-2xl group-hover:rotate-6 transition-all duration-300">
                <i class="fas fa-wallet"></i>
            </div>
        </div>
        <p class="text-[11px] text-slate-500 font-bold uppercase tracking-[0.2em] mb-4">Today's Volume</p>
        <h3 class="text-4xl font-bold text-emerald-400 mb-6 font-heading"><?php echo format_admin_currency($today_revenue); ?></h3>
        <div class="flex items-center gap-2 text-emerald-500/80 text-[10px] font-bold uppercase tracking-widest">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <span>Real-time Sync</span>
        </div>
    </div>

    <!-- Total Reviews -->
    <div class="custom-card p-8 group hover:border-indigo-500/40 relative overflow-hidden">
        <div class="absolute top-0 right-0 p-4">
            <div class="w-14 h-14 rounded-2xl bg-indigo-500/10 flex items-center justify-center text-indigo-500 text-2xl group-hover:rotate-6 transition-all duration-300">
                <i class="fas fa-star"></i>
            </div>
        </div>
        <p class="text-[11px] text-slate-500 font-bold uppercase tracking-[0.2em] mb-4">User Feedback</p>
        <h3 class="text-4xl font-bold text-white mb-6 font-heading"><?php echo number_format($total_reviews); ?></h3>
        <a href="<?php echo admin_url('reviews'); ?>" class="text-indigo-400 text-xs font-bold inline-flex items-center gap-2 group/btn">
            <span>Moderate Feedback</span>
            <i class="fas fa-arrow-right group-hover/btn:translate-x-1 transition-transform"></i>
        </a>
    </div>

    <!-- Total Users -->
    <div class="custom-card p-8 group hover:border-purple-500/40 relative overflow-hidden">
        <div class="absolute top-0 right-0 p-4">
            <div class="w-14 h-14 rounded-2xl bg-purple-500/10 flex items-center justify-center text-purple-500 text-2xl group-hover:rotate-6 transition-all duration-300">
                <i class="fas fa-users"></i>
            </div>
        </div>
        <p class="text-[11px] text-slate-500 font-bold uppercase tracking-[0.2em] mb-4">Global Users</p>
        <h3 class="text-4xl font-bold text-white mb-6 font-heading"><?php echo number_format($total_users); ?></h3>
        <a href="<?php echo admin_url('users'); ?>" class="text-purple-400 text-xs font-bold inline-flex items-center gap-2 group/btn">
            <span>Customer List</span>
            <i class="fas fa-arrow-right group-hover/btn:translate-x-1 transition-transform"></i>
        </a>
    </div>
</div>

<!-- Main Content Grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
    
    <!-- Sales Chart -->
    <div class="lg:col-span-2 custom-card p-8 lg:p-10">
        <div class="flex items-center justify-between mb-10">
            <div>
                <h3 class="font-bold text-xl text-white font-heading">Revenue Overview</h3>
                <p class="text-slate-500 text-xs mt-1">Growth trends over the last 7 active days</p>
            </div>
            <div class="flex gap-2">
                <span class="px-3 py-1.5 rounded-lg bg-indigo-500/10 text-indigo-400 text-[10px] font-bold uppercase tracking-widest border border-indigo-500/20">7 Days</span>
            </div>
        </div>
        <div class="relative h-80 w-full">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    <!-- Recent Reviews Widget -->
    <div class="lg:col-span-1 custom-card p-8 flex flex-col">
        <h3 class="font-bold text-xl text-white mb-8 font-heading">Recent Activity</h3>
        <div class="flex-1 space-y-6">
            <?php if(empty($recent_reviews)): ?>
                <div class="text-center py-12 flex flex-col items-center">
                    <div class="w-16 h-16 rounded-full bg-slate-800/50 flex items-center justify-center text-slate-600 mb-4 border border-white/5">
                        <i class="fas fa-comment-slash text-2xl"></i>
                    </div>
                    <p class="text-xs text-slate-500 font-medium">No reviews logged yet.</p>
                </div>
            <?php else: ?>
                <?php foreach($recent_reviews as $r): ?>
                    <div class="bg-black/20 p-5 rounded-2xl border border-white/5 group hover:border-indigo-500/20 transition-all">
                        <div class="flex justify-between items-start mb-3">
                            <span class="text-xs font-bold text-indigo-400">@<?php echo htmlspecialchars($r['username']); ?></span>
                            <div class="flex text-[9px] text-amber-400 gap-0.5">
                                <?php for($i=1; $i<=5; $i++) echo ($i <= $r['rating']) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star text-slate-700"></i>'; ?>
                            </div>
                        </div>
                        <p class="text-[13px] text-slate-400 mb-3 italic leading-relaxed">"<?php echo htmlspecialchars($r['comment']); ?>"</p>
                        <div class="flex items-center gap-2">
                            <div class="w-1 h-1 rounded-full bg-slate-600"></div>
                            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest truncate"><?php echo htmlspecialchars($r['product_name']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="mt-8 pt-8 border-t border-white/5">
            <a href="<?php echo admin_url('reviews'); ?>" class="group flex items-center justify-center gap-2 text-sm font-bold text-slate-400 hover:text-indigo-400 transition-all">
                <span>View Global Feedback</span>
                <i class="fas fa-chevron-right text-[10px] group-hover:translate-x-1 transition-transform"></i>
            </a>
        </div>
    </div>

    <!-- Recent Orders Table -->
    <div class="lg:col-span-3 custom-card overflow-hidden">
        <div class="p-8 lg:p-10 border-b border-white/5 flex justify-between items-center bg-white/[0.02]">
            <div>
                <h3 class="font-bold text-xl text-white font-heading">Recent Transactions</h3>
                <p class="text-slate-500 text-xs mt-1">Latest marketplace orders processed</p>
            </div>
            <a href="<?php echo admin_url('orders'); ?>" class="bg-slate-800/40 hover:bg-indigo-600 border border-white/5 hover:border-indigo-500 text-white px-6 py-2.5 rounded-xl font-bold text-xs transition-all flex items-center gap-2">
                <span>View All</span>
                <i class="fas fa-arrow-right text-[10px]"></i>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="bg-black/20 text-slate-500 uppercase text-[10px] font-bold tracking-[0.2em]">
                        <th class="p-6 pl-10">Identifier</th>
                        <th class="p-6">Client</th>
                        <th class="p-6">Asset</th>
                        <th class="p-6">Settlement</th>
                        <th class="p-6">Status</th>
                        <th class="p-6 pr-10 text-right">Interaction</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/[0.03]">
                    <?php foreach($orders as $o): ?>
                    <tr class="hover:bg-indigo-500/[0.02] transition-colors group">
                        <td class="p-6 pl-10">
                            <span class="bg-slate-800/50 text-slate-400 px-3 py-1.5 rounded-lg font-mono text-[10px] border border-white/5">
                                #<?php echo $o['id']; ?>
                            </span>
                        </td>
                        <td class="p-6">
                            <div class="flex flex-col">
                                <span class="font-bold text-white"><?php echo htmlspecialchars($o['username']); ?></span>
                                <span class="text-[10px] text-slate-500 font-medium">Customer</span>
                            </div>
                        </td>
                        <td class="p-6">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-slate-800 flex items-center justify-center text-slate-500">
                                    <i class="fas fa-cube text-xs"></i>
                                </div>
                                <span class="text-slate-300 font-medium"><?php echo htmlspecialchars($o['product_name']); ?></span>
                            </div>
                        </td>
                        <td class="p-6">
                            <span class="text-white font-bold tracking-tight"><?php echo format_admin_currency($o['total_price_paid']); ?></span>
                        </td>
                        <td class="p-6">
                            <?php 
                                $statusClass = match($o['status']) {
                                    'completed', 'active' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                    'pending' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                    'cancelled', 'rejected' => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
                                    default => 'bg-slate-500/10 text-slate-400 border-slate-500/20'
                                };
                            ?>
                            <span class="px-4 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-widest border <?php echo $statusClass; ?> shadow-sm">
                                <?php echo $o['status']; ?>
                            </span>
                        </td>
                        <td class="p-6 pr-10 text-right">
                            <a href="<?php echo admin_url('order_detail', ['id' => $o['id']]); ?>" class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-slate-800/50 border border-white/5 text-indigo-400 hover:bg-indigo-600 hover:text-white hover:border-indigo-500 transition-all">
                                <i class="fas fa-arrow-right text-xs"></i>
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
    gradient.addColorStop(0, 'rgba(79, 70, 229, 0.25)'); // Indigo
    gradient.addColorStop(1, 'rgba(79, 70, 229, 0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                label: 'Volume',
                data: <?php echo json_encode($chart_data); ?>,
                borderColor: '#6366f1',
                backgroundColor: gradient,
                borderWidth: 3,
                tension: 0.45,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#6366f1',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1e293b',
                    titleFont: { family: 'Outfit', size: 13, weight: 'bold' },
                    bodyFont: { family: 'Inter', size: 12 },
                    padding: 12,
                    cornerRadius: 12,
                    displayColors: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(255,255,255,0.03)', drawBorder: false },
                    ticks: { color: '#64748b', font: { family: 'Inter', size: 11 } }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#64748b', font: { family: 'Inter', size: 11 } }
                }
            }
        }
    });
</script>