<?php
// modules/user/dashboard.php

// 1. Security: Protect Route
if (!is_logged_in()) {
    redirect('index.php?module=auth&page=login');
}

$user_id = $_SESSION['user_id'];

// 2. Fetch User Data
$stmt = $pdo->prepare("SELECT full_name, email, avatar_path, created_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Fetch Wishlist Count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
$stmt->execute([$user_id]);
$wishlist_count = $stmt->fetchColumn();

// 3. Fetch Quick Stats
// Order Count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
$stmt->execute([$user_id]);
$order_count = $stmt->fetchColumn();

// Referral Count (Try catch in case referred_by doesn't exist yet)
$referral_count = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by = ?");
    $stmt->execute([$user_id]);
    $referral_count = $stmt->fetchColumn();
} catch (PDOException $e) {}

// Spending summary metrics (status = 'active')
// Total Spent (All Time)
$stmt = $pdo->prepare("SELECT SUM(total_price_paid) FROM orders WHERE user_id = ? AND status = 'active'");
$stmt->execute([$user_id]);
$total_spent = (float)($stmt->fetchColumn() ?? 0.0);

// Spent This Month
$stmt = $pdo->prepare("SELECT SUM(total_price_paid) FROM orders WHERE user_id = ? AND status = 'active' AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00')");
$stmt->execute([$user_id]);
$spent_this_month = (float)($stmt->fetchColumn() ?? 0.0);

// Total Saved via Discounts
$stmt = $pdo->prepare("
    SELECT SUM(p.price - o.total_price_paid) 
    FROM orders o
    JOIN products p ON o.product_id = p.id
    WHERE o.user_id = ? AND o.status = 'active'
");
$stmt->execute([$user_id]);
$saved_all_time = (float)($stmt->fetchColumn() ?? 0.0);
if ($saved_all_time < 0) $saved_all_time = 0.0;

// Saved via Discounts (This Month)
$stmt = $pdo->prepare("
    SELECT SUM(p.price - o.total_price_paid) 
    FROM orders o
    JOIN products p ON o.product_id = p.id
    WHERE o.user_id = ? AND o.status = 'active' AND o.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00')
");
$stmt->execute([$user_id]);
$saved_this_month = (float)($stmt->fetchColumn() ?? 0.0);
if ($saved_this_month < 0) $saved_this_month = 0.0;

// 4. Fetch Recent Orders (Limit 5)
// FIXED: Changed 'o.total_amount' to 'o.total_price_paid'
// FIXED: Changed 'p.title' to 'p.name'
$stmt = $pdo->prepare("
    SELECT o.id, o.total_price_paid, o.status, o.created_at, p.name 
    FROM orders o 
    JOIN products p ON o.product_id = p.id 
    WHERE o.user_id = ? 
    ORDER BY o.created_at DESC LIMIT 5
");
$stmt->execute([$user_id]);
$recent_orders = $stmt->fetchAll();
?>

<div class="max-w-7xl mx-auto px-6 py-12">

    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-12 gap-8">
        <div class="flex items-center gap-6">
            <?php if (!empty($user['avatar_path']) && file_exists($user['avatar_path'])): ?>
                <img src="<?php echo htmlspecialchars($user['avatar_path']); ?>" alt="Avatar" class="w-16 h-16 md:w-20 md:h-20 rounded-full object-cover border border-blue-500/30 shadow-lg">
            <?php else: ?>
                <div class="w-16 h-16 md:w-20 md:h-20 bg-gradient-to-br from-blue-600 to-indigo-800 rounded-full flex items-center justify-center text-2xl md:text-3xl font-bold text-white shadow-lg border border-blue-400/30">
                    <?php 
                        $initial = !empty($user['full_name']) ? substr($user['full_name'], 0, 1) : (!empty($user['username']) ? substr($user['username'], 0, 1) : 'U');
                        echo strtoupper($initial); 
                    ?>
                </div>
            <?php endif; ?>
            <div>
                <h1 class="text-3xl md:text-5xl font-bold text-white tracking-tight">Account Overview</h1>
                <p class="text-slate-500 mt-2 text-lg">Welcome back, <span class="text-blue-500 font-bold"><?php echo htmlspecialchars($user['full_name']); ?></span></p>
            </div>
        </div>
        <div class="flex gap-4 shrink-0">
            <a href="index.php?module=shop&page=search" class="bg-blue-600 hover:bg-blue-500 text-white px-8 py-3.5 rounded-2xl font-bold transition-all active:scale-95 shadow-lg shadow-blue-500/20">
                Browse Store
            </a>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
        <div class="bg-slate-800/20 border border-white/5 p-8 rounded-[2.5rem] flex items-center justify-between group hover:border-blue-500/30 transition-all">
            <div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Total Orders</p>
                <h3 class="text-4xl font-bold text-white"><?php echo $order_count; ?></h3>
            </div>
            <div class="w-16 h-16 rounded-2xl bg-blue-500/10 flex items-center justify-center text-blue-400 text-2xl group-hover:scale-110 transition-transform">
                <i class="fas fa-shopping-bag"></i>
            </div>
        </div>

        <div class="bg-slate-800/20 border border-white/5 p-8 rounded-[2.5rem] flex items-center justify-between group hover:border-rose-500/30 transition-all">
            <div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Saved Wishlist</p>
                <h3 class="text-4xl font-bold text-rose-400"><?php echo $wishlist_count; ?> <span class="text-sm font-medium text-slate-500 ml-1">Items</span></h3>
            </div>
            <div class="w-16 h-16 rounded-2xl bg-rose-500/10 flex items-center justify-center text-rose-400 text-2xl group-hover:scale-110 transition-transform">
                <i class="fas fa-heart"></i>
            </div>
        </div>

        <div class="bg-slate-800/20 border border-white/5 p-8 rounded-[2.5rem] flex flex-col justify-between group hover:border-emerald-500/30 transition-all">
            <div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-4">Spending Summary</p>
                <div class="space-y-2.5">
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-slate-400">Total Spent</span>
                        <span class="font-bold text-white font-mono"><?php echo number_format($total_spent); ?> Ks</span>
                    </div>
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-slate-400">This Month</span>
                        <span class="font-bold text-white font-mono"><?php echo number_format($spent_this_month); ?> Ks</span>
                    </div>
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-slate-400">Saved via Discounts</span>
                        <span class="font-bold text-emerald-400 font-mono"><?php echo number_format($saved_all_time); ?> Ks</span>
                    </div>
                </div>
            </div>
            <?php if ($saved_this_month > 0): ?>
                <div class="mt-4 pt-3 border-t border-white/5 text-[10px] text-emerald-400 font-bold flex items-center gap-1.5 leading-tight">
                    <i class="fas fa-gift text-emerald-400 animate-bounce"></i> 
                    <span>You saved <?php echo number_format($saved_this_month); ?> Ks this month with your agent discount!</span>
                </div>
            <?php else: ?>
                <div class="mt-4 pt-3 border-t border-white/5 text-[10px] text-slate-400 font-medium flex items-center gap-1.5 leading-tight">
                    <i class="fas fa-crown text-yellow-500"></i> 
                    <span>Unlock extra savings! <a href="index.php?module=user&page=agent" class="text-blue-500 hover:text-blue-400 font-bold underline">Get Agent Pass</a></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">

        <!-- Orders -->
        <div class="lg:col-span-8 space-y-8">
            <div class="bg-slate-800/20 rounded-[2.5rem] border border-white/5 overflow-hidden">
                <div class="p-8 border-b border-white/5 flex justify-between items-center bg-white/5">
                    <h3 class="font-bold text-white flex items-center gap-3">
                        <i class="fas fa-history text-slate-500"></i> Recent Orders
                    </h3>
                    <a href="index.php?module=user&page=orders" class="text-sm font-bold text-blue-500 hover:text-blue-400">View All</a>
                </div>

                <div class="overflow-x-auto">
                    <?php if (empty($recent_orders)): ?>
                        <div class="p-20 text-center space-y-4">
                            <div class="w-16 h-16 bg-slate-900 rounded-2xl flex items-center justify-center mx-auto text-slate-700 text-2xl">
                                <i class="fas fa-box-open"></i>
                            </div>
                            <p class="text-slate-500 font-medium">No orders found yet.</p>
                            <a href="index.php?module=shop&page=search" class="text-blue-500 font-bold hover:underline">Start shopping</a>
                        </div>
                    <?php else: ?>
                        <table class="w-full text-left">
                            <thead class="bg-slate-900/50 text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                                <tr>
                                    <th class="px-8 py-5">Order ID</th>
                                    <th class="px-8 py-5">Product</th>
                                    <th class="px-8 py-5">Status</th>
                                    <th class="px-8 py-5 text-right">Price</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <?php foreach($recent_orders as $order): ?>
                                    <tr class="hover:bg-white/5 transition-colors cursor-pointer" onclick="window.location='index.php?module=user&page=orders&view_chat=<?php echo $order['id']; ?>'">
                                        <td class="px-8 py-6 font-mono text-xs text-slate-400">#<?php echo $order['id']; ?></td>
                                        <td class="px-8 py-6 text-sm font-bold text-white truncate max-w-[200px]"><?php echo htmlspecialchars($order['name']); ?></td>
                                        <td class="px-8 py-6">
                                            <?php 
                                                $statusClass = match($order['status']) {
                                                    'completed', 'active' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                                    'pending' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                                    'cancelled', 'rejected' => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
                                                    default => 'bg-slate-500/10 text-slate-400 border-slate-500/20'
                                                };
                                            ?>
                                            <span class="px-3 py-1 rounded-lg text-[10px] font-bold uppercase tracking-widest border <?php echo $statusClass; ?>">
                                                <?php echo $order['status']; ?>
                                            </span>
                                        </td>
                                        <td class="px-8 py-6 text-right font-bold text-white text-sm">
                                            <?php echo number_format($order['total_price_paid']); ?> <span class="text-[10px] text-slate-500">Ks</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Links -->
        <div class="lg:col-span-4 space-y-6">
            <a href="index.php?module=user&page=profile" class="flex items-center justify-between p-6 bg-slate-800/20 border border-white/5 rounded-3xl hover:bg-slate-800/40 transition-all group">
                <div class="flex items-center gap-5">
                    <div class="w-12 h-12 rounded-2xl bg-slate-900 flex items-center justify-center text-slate-500 group-hover:text-white transition-colors">
                        <i class="fas fa-user-edit"></i>
                    </div>
                    <div>
                        <h4 class="text-white font-bold text-sm">Edit Profile</h4>
                        <p class="text-[10px] text-slate-500 uppercase font-bold tracking-widest mt-1">Manage details</p>
                    </div>
                </div>
                <i class="fas fa-chevron-right text-slate-700 group-hover:text-blue-500 transition-colors"></i>
            </a>

            <a href="index.php?module=user&page=wishlist" class="flex items-center justify-between p-6 bg-slate-800/20 border border-white/5 rounded-3xl hover:bg-slate-800/40 transition-all group">
                <div class="flex items-center gap-5">
                    <div class="w-12 h-12 rounded-2xl bg-rose-500/10 flex items-center justify-center text-rose-500 group-hover:scale-110 transition-transform">
                        <i class="fas fa-heart"></i>
                    </div>
                    <div>
                        <h4 class="text-white font-bold text-sm">My Wishlist</h4>
                        <p class="text-[10px] text-slate-500 uppercase font-bold tracking-widest mt-1">Saved items</p>
                    </div>
                </div>
                <i class="fas fa-chevron-right text-slate-700 group-hover:text-rose-500 transition-colors"></i>
            </a>

            <a href="index.php?module=info&page=support" class="flex items-center justify-between p-6 bg-slate-800/20 border border-white/5 rounded-3xl hover:bg-slate-800/40 transition-all group">
                <div class="flex items-center gap-5">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 flex items-center justify-center text-indigo-400 group-hover:scale-110 transition-transform">
                        <i class="fas fa-headset"></i>
                    </div>
                    <div>
                        <h4 class="text-white font-bold text-sm">Help Center</h4>
                        <p class="text-[10px] text-slate-500 uppercase font-bold tracking-widest mt-1">Get support</p>
                    </div>
                </div>
                <i class="fas fa-chevron-right text-slate-700 group-hover:text-indigo-500 transition-colors"></i>
            </a>

            <a href="index.php?module=auth&page=logout" class="flex items-center justify-center gap-3 p-5 mt-6 w-full bg-rose-500/5 hover:bg-rose-500/10 text-rose-500 border border-rose-500/10 rounded-3xl transition-all font-bold text-sm">
                <i class="fas fa-power-off"></i> Logout
            </a>
        </div>

    </div>
</div>