<?php
// admin/pandl.php
// PRODUCTION v2.0 - Advanced P&L Matrix with Temporal Scoping & Trend Analytics

// 1. Handle Batch Cost Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_costs'])) {
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO product_costs (product_id, cost_price) VALUES (?, ?) ON DUPLICATE KEY UPDATE cost_price = ?");
        
        if (isset($_POST['cost']) && is_array($_POST['cost'])) {
            foreach ($_POST['cost'] as $pid => $cost) {
                $clean_cost = (float)abs($cost);
                $stmt->execute([(int)$pid, $clean_cost, $clean_cost]);
            }
        }
        $pdo->commit();
        redirect(admin_url('pandl', ['success' => 1, 'range' => $_GET['range'] ?? '30']));
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Data synchronization failed: " . $e->getMessage();
    }
}

// =====================================================================================
// NEW FUNCTION 1: DYNAMIC TEMPORAL SCOPING
// =====================================================================================
$range = isset($_GET['range']) ? $_GET['range'] : '30';
$date_filter = "";
$prev_date_filter = "";
$chart_days = 14; // Default chart span

if ($range === '7') {
    $date_filter = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $prev_date_filter = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND o.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $chart_days = 7;
} elseif ($range === '30') {
    $date_filter = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $prev_date_filter = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND o.created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $chart_days = 30;
} else {
    // Lifetime (No date filter)
    $date_filter = "";
    $prev_date_filter = "AND 1=0"; // Prevent previous calc for lifetime
    $chart_days = 30; // Cap chart at 30 days for performance on lifetime view
}

// 2. Fetch Global Financial Telemetry (Current Period)
$sql_stats = "
    SELECT 
        COUNT(o.id) as total_sales,
        SUM(o.total_price_paid) as total_revenue,
        SUM(CASE WHEN o.product_id IS NOT NULL THEN COALESCE(pc.cost_price, 0) ELSE 0 END) as total_cost
    FROM orders o
    LEFT JOIN product_costs pc ON o.product_id = pc.product_id
    WHERE o.status = 'active' $date_filter
";
$global_stats = $pdo->query($sql_stats)->fetch();

$total_revenue = $global_stats['total_revenue'] ?: 0;
$total_cost = $global_stats['total_cost'] ?: 0;
$total_profit = $total_revenue - $total_cost;
$avg_margin = $total_revenue > 0 ? round(($total_profit / $total_revenue) * 100, 1) : 0;

// =====================================================================================
// NEW FUNCTION 2: VELOCITY TREND ENGINE (Previous Period Comparison)
// =====================================================================================
$sql_prev_stats = "
    SELECT 
        SUM(o.total_price_paid) as total_revenue,
        SUM(CASE WHEN o.product_id IS NOT NULL THEN COALESCE(pc.cost_price, 0) ELSE 0 END) as total_cost
    FROM orders o
    LEFT JOIN product_costs pc ON o.product_id = pc.product_id
    WHERE o.status = 'active' $prev_date_filter
";
$prev_stats = $pdo->query($sql_prev_stats)->fetch();

$prev_revenue = $prev_stats['total_revenue'] ?: 0;
$prev_profit = $prev_revenue - ($prev_stats['total_cost'] ?: 0);
$prev_margin = $prev_revenue > 0 ? round(($prev_profit / $prev_revenue) * 100, 1) : 0;

// Calculate Trend Percentages
function calc_trend($current, $previous) {
    if ($previous == 0) return $current > 0 ? 100 : 0;
    return round((($current - $previous) / $previous) * 100, 1);
}

$trend_rev = calc_trend($total_revenue, $prev_revenue);
$trend_profit = calc_trend($total_profit, $prev_profit);
$trend_margin = $avg_margin - $prev_margin; // Absolute difference for margins

// Trend HTML Builder
function render_trend($value, $is_margin = false) {
    $symbol = $value >= 0 ? '↑' : '↓';
    $color = $value >= 0 ? 'text-green-400' : 'text-red-400';
    $suffix = $is_margin ? 'pts' : '%';
    return "<span class='text-[10px] font-bold $color bg-slate-900/50 px-1.5 py-0.5 rounded-md border border-slate-700/50'>$symbol " . abs($value) . "$suffix</span>";
}

// 3. Fetch Chart Data (Dynamic Days)
$chart_labels = [];
$chart_revenue = [];
$chart_profit = [];

for ($i = $chart_days - 1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('M d', strtotime($date));
    
    $day_sql = "
        SELECT 
            SUM(o.total_price_paid) as d_rev,
            SUM(CASE WHEN o.product_id IS NOT NULL THEN COALESCE(pc.cost_price, 0) ELSE 0 END) as d_cost
        FROM orders o
        LEFT JOIN product_costs pc ON o.product_id = pc.product_id
        WHERE o.status = 'active' AND DATE(o.created_at) = '$date'
    ";
    $day_stats = $pdo->query($day_sql)->fetch();
    
    $d_rev = $day_stats['d_rev'] ?: 0;
    $chart_revenue[] = $d_rev;
    $chart_profit[] = $d_rev - ($day_stats['d_cost'] ?: 0);
}

// =====================================================================================
// NEW FUNCTION 3: ASSET INSIGHT ENGINE (Top / Bottom Performers)
// =====================================================================================
$sql_insights = "
    SELECT 
        p.name, 
        p.image_path,
        SUM(o.total_price_paid) as revenue,
        SUM(o.total_price_paid - COALESCE(pc.cost_price, 0)) as profit,
        (SUM(o.total_price_paid - COALESCE(pc.cost_price, 0)) / SUM(o.total_price_paid) * 100) as margin
    FROM orders o
    JOIN products p ON o.product_id = p.id
    LEFT JOIN product_costs pc ON p.id = pc.product_id
    WHERE o.status = 'active' $date_filter
    GROUP BY p.id
    HAVING revenue > 0
";

$top_node = $pdo->query("$sql_insights ORDER BY profit DESC LIMIT 1")->fetch();
$critical_node = $pdo->query("$sql_insights ORDER BY margin ASC LIMIT 1")->fetch();

// 4. Fetch Products with their Cost Prices for the Data Table
$products = $pdo->query("
    SELECT p.id, p.name, p.price, p.sale_price, c.name as cat_name, COALESCE(pc.cost_price, 0) as cost_price,
    (SELECT COUNT(*) FROM orders o WHERE o.product_id = p.id AND o.status = 'active') as sales_count
    FROM products p
    JOIN categories c ON p.category_id = c.id
    LEFT JOIN product_costs pc ON p.id = pc.product_id
    ORDER BY sales_count DESC, p.id DESC
")->fetchAll();
?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 relative z-10">
    <div>
        <h1 class="text-3xl font-bold text-white tracking-tight flex items-center gap-3">
            Profit & Loss Matrix <i class="fas fa-chart-line text-[#00f0ff]"></i>
        </h1>
        <p class="text-slate-400 text-sm mt-1">Real-time financial telemetry, acquisition costs, and net margins.</p>
    </div>

    <!-- NEW FEATURE 1: TIME-PHASE TOGGLE -->
    <div class="flex items-center bg-slate-900 border border-slate-700 rounded-xl p-1 shadow-inner">
        <a href="?page=pandl&range=7" class="px-5 py-2 rounded-lg text-xs font-bold transition-all <?php echo $range == '7' ? 'bg-gradient-to-r from-blue-600 to-[#00f0ff] text-slate-900 shadow-[0_0_10px_rgba(0,240,255,0.4)]' : 'text-slate-400 hover:text-white'; ?>">7 Days</a>
        <a href="?page=pandl&range=30" class="px-5 py-2 rounded-lg text-xs font-bold transition-all <?php echo $range == '30' ? 'bg-gradient-to-r from-blue-600 to-[#00f0ff] text-slate-900 shadow-[0_0_10px_rgba(0,240,255,0.4)]' : 'text-slate-400 hover:text-white'; ?>">30 Days</a>
        <a href="?page=pandl&range=all" class="px-5 py-2 rounded-lg text-xs font-bold transition-all <?php echo $range == 'all' ? 'bg-gradient-to-r from-blue-600 to-[#00f0ff] text-slate-900 shadow-[0_0_10px_rgba(0,240,255,0.4)]' : 'text-slate-400 hover:text-white'; ?>">Lifetime</a>
    </div>
</div>

<?php if(isset($_GET['success'])): ?>
    <div class="bg-green-500/10 border border-green-500/30 text-green-400 p-4 rounded-xl mb-6 flex items-center gap-3 shadow-[0_0_15px_rgba(34,197,94,0.15)] animate-fade-in-down">
        <i class="fas fa-check-circle"></i> Cost parameters synchronized successfully.
    </div>
<?php endif; ?>

<!-- KPI Dashboard with Momentum Badges -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8 relative z-10">
    
    <div class="bg-slate-900/80 backdrop-blur-xl p-6 rounded-2xl border border-slate-700 shadow-[0_10px_30px_rgba(0,0,0,0.3)] relative overflow-hidden group hover:border-blue-500/50 transition">
        <div class="absolute -right-6 -top-6 w-24 h-24 bg-blue-500/10 rounded-full blur-2xl group-hover:bg-blue-500/20 transition"></div>
        <div class="flex justify-between items-start mb-1">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Gross Revenue</p>
            <?php if($range !== 'all') echo render_trend($trend_rev); ?>
        </div>
        <h3 class="text-2xl md:text-3xl font-black text-white font-mono mt-1"><?php echo format_admin_currency($total_revenue); ?></h3>
        <i class="fas fa-money-bill-wave absolute right-6 bottom-6 text-3xl text-slate-700 opacity-30 group-hover:text-blue-500/30 transition transform group-hover:scale-110"></i>
    </div>

    <div class="bg-slate-900/80 backdrop-blur-xl p-6 rounded-2xl border border-slate-700 shadow-[0_10px_30px_rgba(0,0,0,0.3)] relative overflow-hidden group hover:border-red-500/50 transition">
        <div class="absolute -right-6 -top-6 w-24 h-24 bg-red-500/10 rounded-full blur-2xl group-hover:bg-red-500/20 transition"></div>
        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Acquisition Costs</p>
        <h3 class="text-2xl md:text-3xl font-black text-red-400 font-mono mt-1">-<?php echo format_admin_currency($total_cost); ?></h3>
        <i class="fas fa-shopping-cart absolute right-6 bottom-6 text-3xl text-slate-700 opacity-30 group-hover:text-red-500/30 transition transform group-hover:scale-110"></i>
    </div>

    <div class="bg-slate-900/80 backdrop-blur-xl p-6 rounded-2xl border border-slate-700 shadow-[0_10px_30px_rgba(0,0,0,0.3)] relative overflow-hidden group hover:border-green-500/50 transition">
        <div class="absolute -right-6 -top-6 w-24 h-24 bg-green-500/10 rounded-full blur-2xl group-hover:bg-green-500/20 transition"></div>
        <div class="flex justify-between items-start mb-1">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Net Profit</p>
            <?php if($range !== 'all') echo render_trend($trend_profit); ?>
        </div>
        <h3 class="text-2xl md:text-3xl font-black text-green-400 font-mono mt-1"><?php echo format_admin_currency($total_profit); ?></h3>
        <i class="fas fa-chart-pie absolute right-6 bottom-6 text-3xl text-slate-700 opacity-30 group-hover:text-green-500/30 transition transform group-hover:scale-110"></i>
    </div>

    <div class="bg-slate-900/80 backdrop-blur-xl p-6 rounded-2xl border border-slate-700 shadow-[0_10px_30px_rgba(0,0,0,0.3)] relative overflow-hidden group hover:border-purple-500/50 transition">
        <div class="absolute -right-6 -top-6 w-24 h-24 bg-purple-500/10 rounded-full blur-2xl group-hover:bg-purple-500/20 transition"></div>
        <div class="flex justify-between items-start mb-1">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Avg Margin</p>
            <?php if($range !== 'all') echo render_trend($trend_margin, true); ?>
        </div>
        <h3 class="text-2xl md:text-3xl font-black <?php echo $avg_margin >= 30 ? 'text-purple-400' : 'text-yellow-400'; ?> font-mono mt-1"><?php echo $avg_margin; ?>%</h3>
        <i class="fas fa-percent absolute right-6 bottom-6 text-3xl text-slate-700 opacity-30 group-hover:text-purple-500/30 transition transform group-hover:scale-110"></i>
    </div>

</div>

<!-- NEW FEATURE 2 & 3: INTELLIGENCE PANELS -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10 relative z-10">
    
    <!-- Top Yield Node -->
    <div class="bg-slate-900/60 backdrop-blur-xl rounded-2xl border border-emerald-500/30 p-5 shadow-lg flex items-center gap-4 relative overflow-hidden group">
        <div class="absolute inset-0 bg-gradient-to-r from-emerald-500/5 to-transparent pointer-events-none"></div>
        <div class="w-14 h-14 rounded-xl bg-slate-950 border border-emerald-500/50 flex items-center justify-center text-emerald-400 shrink-0 shadow-[0_0_15px_rgba(16,185,129,0.2)] overflow-hidden relative">
            <?php if(!empty($top_node['image_path'])): ?>
                <img src="<?php echo MAIN_SITE_URL . $top_node['image_path']; ?>" class="w-full h-full object-cover opacity-80 group-hover:scale-110 transition duration-500">
            <?php else: ?>
                <i class="fas fa-rocket text-xl group-hover:-translate-y-1 transition transform"></i>
            <?php endif; ?>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-[10px] font-black text-emerald-400 uppercase tracking-widest mb-0.5"><i class="fas fa-crown mr-1"></i> Top Yield Node</p>
            <h4 class="text-white font-bold text-sm truncate"><?php echo $top_node ? htmlspecialchars($top_node['name']) : 'Insufficient Data'; ?></h4>
            <p class="text-xs text-slate-400 mt-1 font-mono">Profit: <span class="text-emerald-400 font-bold"><?php echo $top_node ? format_admin_currency($top_node['profit']) : '0 Ks'; ?></span></p>
        </div>
    </div>

    <!-- Critical Margin Alert -->
    <div class="bg-slate-900/60 backdrop-blur-xl rounded-2xl border border-orange-500/30 p-5 shadow-lg flex items-center gap-4 relative overflow-hidden group">
        <div class="absolute inset-0 bg-gradient-to-r from-orange-500/5 to-transparent pointer-events-none"></div>
        <div class="w-14 h-14 rounded-xl bg-slate-950 border border-orange-500/50 flex items-center justify-center text-orange-400 shrink-0 shadow-[0_0_15px_rgba(249,115,22,0.2)] overflow-hidden relative">
            <?php if(!empty($critical_node['image_path'])): ?>
                <img src="<?php echo MAIN_SITE_URL . $critical_node['image_path']; ?>" class="w-full h-full object-cover opacity-80 group-hover:scale-110 transition duration-500">
            <?php else: ?>
                <i class="fas fa-exclamation-triangle text-xl group-hover:scale-110 transition transform"></i>
            <?php endif; ?>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-[10px] font-black text-orange-400 uppercase tracking-widest mb-0.5"><i class="fas fa-engine-warning mr-1"></i> Critical Margin Alert</p>
            <h4 class="text-white font-bold text-sm truncate"><?php echo $critical_node ? htmlspecialchars($critical_node['name']) : 'Insufficient Data'; ?></h4>
            <p class="text-xs text-slate-400 mt-1 font-mono">Margin: <span class="text-orange-400 font-bold"><?php echo $critical_node ? round($critical_node['margin'], 1) . '%' : '0%'; ?></span></p>
        </div>
    </div>

</div>

<!-- Chart Section -->
<div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl border border-slate-700 shadow-2xl p-6 mb-10 relative overflow-hidden">
    <div class="absolute top-0 right-0 w-96 h-96 bg-blue-600/5 rounded-full blur-3xl pointer-events-none"></div>
    <div class="flex justify-between items-center mb-6">
        <h3 class="font-bold text-white text-lg tracking-tight"><i class="fas fa-project-diagram text-[#00f0ff] mr-2"></i> <?php echo $chart_days; ?>-Day Trajectory</h3>
    </div>
    <div class="relative h-[300px] w-full">
        <canvas id="pandlChart"></canvas>
    </div>
</div>

<!-- Cost Configuration Table -->
<form method="POST" class="bg-slate-900/60 backdrop-blur-xl rounded-3xl border border-slate-700 overflow-hidden shadow-2xl flex flex-col relative z-10">
    <div class="p-5 border-b border-slate-700/80 bg-slate-800/40 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 shrink-0">
        <div>
            <h3 class="font-bold text-white text-lg flex items-center gap-2"><i class="fas fa-tags text-slate-400"></i> Asset Cost Configuration</h3>
            <p class="text-xs text-slate-400 mt-1">Define the base cost for each node to enable accurate profit tracking.</p>
        </div>
        <button type="submit" name="update_costs" class="bg-gradient-to-r from-blue-600 to-[#00f0ff] hover:from-blue-500 hover:to-[#00f0ff] text-slate-900 font-black px-6 py-2.5 rounded-xl shadow-[0_0_15px_rgba(0,240,255,0.3)] transition transform active:scale-95 text-xs uppercase tracking-widest flex items-center justify-center gap-2">
            <i class="fas fa-save"></i> Sync Matrix
        </button>
    </div>
    
    <div class="overflow-x-auto flex-grow custom-scrollbar max-h-[600px]">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-800/60 text-slate-400 uppercase text-[10px] font-bold tracking-widest sticky top-0 z-20 backdrop-blur">
                <tr>
                    <th class="p-4 pl-6">Product Node</th>
                    <th class="p-4 text-right">Retail Value</th>
                    <th class="p-4 text-right bg-slate-800/50 text-[#00f0ff]">Real Cost (Input)</th>
                    <th class="p-4 text-right">Est. Profit</th>
                    <th class="p-4 text-right pr-6">Margin %</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/50">
                <?php foreach($products as $p): 
                    $active_price = $p['sale_price'] ?: $p['price'];
                    $est_profit = $active_price - $p['cost_price'];
                    $margin_pct = $active_price > 0 ? round(($est_profit / $active_price) * 100, 1) : 0;
                    $margin_color = $margin_pct >= 30 ? 'text-green-400' : ($margin_pct > 0 ? 'text-yellow-400' : 'text-red-400');
                ?>
                    <tr class="hover:bg-slate-800/40 transition-colors group">
                        <td class="p-4 pl-6">
                            <div class="font-bold text-white text-sm truncate max-w-[250px]" title="<?php echo htmlspecialchars($p['name']); ?>"><?php echo htmlspecialchars($p['name']); ?></div>
                            <div class="text-[10px] text-slate-500 font-mono mt-1">Sector: <?php echo htmlspecialchars($p['cat_name']); ?> • Sales: <?php echo $p['sales_count']; ?></div>
                        </td>
                        
                        <td class="p-4 text-right align-middle font-mono font-bold text-slate-300">
                            <?php echo format_admin_currency($active_price); ?>
                        </td>
                        
                        <!-- Interactive Cost Input -->
                        <td class="p-4 text-right align-middle bg-slate-800/20 group-hover:bg-slate-800/50 transition">
                            <div class="relative inline-block w-32">
                                <input type="number" step="0.01" name="cost[<?php echo $p['id']; ?>]" value="<?php echo $p['cost_price']; ?>" 
                                       class="w-full bg-slate-950 border border-slate-600 rounded-lg py-2 pl-3 pr-8 text-right text-[#00f0ff] font-mono font-bold focus:border-[#00f0ff] focus:ring-1 focus:ring-[#00f0ff] outline-none shadow-inner transition">
                                <span class="absolute right-3 top-2.5 text-[10px] text-slate-500 font-bold pointer-events-none">Ks</span>
                            </div>
                        </td>

                        <td class="p-4 text-right align-middle font-mono font-bold <?php echo $est_profit >= 0 ? 'text-green-400' : 'text-red-400'; ?>">
                            <?php echo ($est_profit > 0 ? '+' : '') . format_admin_currency($est_profit); ?>
                        </td>
                        
                        <td class="p-4 text-right pr-6 align-middle font-mono font-bold <?php echo $margin_color; ?>">
                            <?php echo $margin_pct; ?>%
                        </td>
                    </tr>
                <?php endforeach; ?>
                
                <?php if(empty($products)): ?>
                    <tr>
                        <td colspan="5" class="p-12 text-center text-slate-500">
                            <i class="fas fa-box-open text-4xl mb-3 opacity-30"></i>
                            <p class="font-medium tracking-wide">Matrix is empty. Deploy products first.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</form>

<script>
    // Chart Render Logic
    const ctx = document.getElementById('pandlChart').getContext('2d');
    
    const revGradient = ctx.createLinearGradient(0, 0, 0, 400);
    revGradient.addColorStop(0, 'rgba(0, 240, 255, 0.4)');
    revGradient.addColorStop(1, 'rgba(0, 240, 255, 0)');

    const profitGradient = ctx.createLinearGradient(0, 0, 0, 400);
    profitGradient.addColorStop(0, 'rgba(34, 197, 94, 0.4)');
    profitGradient.addColorStop(1, 'rgba(34, 197, 94, 0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [
                {
                    label: 'Gross Revenue',
                    data: <?php echo json_encode($chart_revenue); ?>,
                    borderColor: '#00f0ff',
                    backgroundColor: revGradient,
                    borderWidth: 2,
                    pointBackgroundColor: '#0f172a',
                    pointBorderColor: '#00f0ff',
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Net Profit',
                    data: <?php echo json_encode($chart_profit); ?>,
                    borderColor: '#22c55e',
                    backgroundColor: profitGradient,
                    borderWidth: 2,
                    pointBackgroundColor: '#0f172a',
                    pointBorderColor: '#22c55e',
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.4,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: { 
                legend: { 
                    display: true,
                    labels: { color: '#94a3b8', usePointStyle: true, boxWidth: 8 }
                },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.9)',
                    titleColor: '#fff',
                    bodyColor: '#cbd5e1',
                    borderColor: 'rgba(0, 240, 255, 0.2)',
                    borderWidth: 1,
                    padding: 10,
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) label += ': ';
                            if (context.parsed.y !== null) {
                                label += new Intl.NumberFormat('en-US').format(context.parsed.y) + ' Ks';
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { 
                        color: '#94a3b8', 
                        font: { size: 10, family: 'monospace' },
                        callback: function(value) {
                            return value >= 1000 ? (value/1000) + 'k' : value;
                        }
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#94a3b8', font: { size: 10 } }
                }
            }
        }
    });
</script>