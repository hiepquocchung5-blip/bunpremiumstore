<?php
// modules/user/dashboard.php

// 1. Security: Protect Route
if (!is_logged_in()) {
    redirect('index.php?module=auth&page=login');
}

$user_id = $_SESSION['user_id'];

// 2. Fetch User Data (Checking if wallet exists, falling back to 0 if not)
$stmt = $pdo->prepare("SELECT full_name, email, created_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Fetch Wallet Balance if it exists (from the referral system update)
$wallet_balance = 0;
try {
    $stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $wallet_balance = $stmt->fetchColumn() ?: 0;
} catch(PDOException $e) {
    // Ignore if wallet column isn't created yet
}

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

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 animate-fade-in-down">
    
    <!-- Dashboard Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-bold text-white tracking-tight flex items-center gap-3">
                Overview <span class="h-2 w-2 rounded-full bg-[#00f0ff] shadow-[0_0_10px_#00f0ff] animate-pulse"></span>
            </h1>
            <p class="text-slate-400 mt-1">Welcome back, <span class="text-[#00f0ff] font-medium"><?php echo htmlspecialchars($user['full_name']); ?></span></p>
        </div>
        <div class="flex gap-3">
            <a href="index.php?module=shop&page=category" class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white px-5 py-2.5 rounded-xl font-bold text-sm shadow-lg shadow-blue-900/20 transition flex items-center gap-2">
                <i class="fas fa-shopping-cart"></i> Browse Store
            </a>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        
        <!-- Wallet Card (Neon Cyberpunk Vibe) -->
        <div class="relative bg-slate-900/80 backdrop-blur-xl border border-[#00f0ff]/30 rounded-2xl p-6 overflow-hidden group hover:border-[#00f0ff]/60 transition-all duration-300 shadow-[0_0_15px_rgba(0,240,255,0.05)] hover:shadow-[0_0_25px_rgba(0,240,255,0.15)]">
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-[#00f0ff]/10 rounded-full blur-2xl group-hover:bg-[#00f0ff]/20 transition"></div>
            <div class="flex justify-between items-start mb-4">
                <div class="w-10 h-10 rounded-lg bg-[#00f0ff]/10 flex items-center justify-center border border-[#00f0ff]/20">
                    <i class="fas fa-wallet text-[#00f0ff]"></i>
                </div>
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider bg-slate-800 px-2 py-1 rounded-md">Wallet</span>
            </div>
            <h3 class="text-3xl font-black text-white"><?php echo number_format($wallet_balance); ?> <span class="text-sm text-[#00f0ff]">MMK</span></h3>
            <p class="text-xs text-slate-400 mt-2 flex items-center gap-1">
                <i class="fas fa-arrow-up text-green-400"></i> Available for purchases
            </p>
        </div>

        <!-- Orders Card -->
        <div class="bg-slate-900/60 backdrop-blur-xl border border-slate-700/50 rounded-2xl p-6 hover:border-blue-500/30 transition-all duration-300 group">
            <div class="flex justify-between items-start mb-4">
                <div class="w-10 h-10 rounded-lg bg-blue-500/10 flex items-center justify-center border border-blue-500/20 text-blue-400 group-hover:scale-110 transition">
                    <i class="fas fa-box-open"></i>
                </div>
            </div>
            <h3 class="text-3xl font-bold text-white"><?php echo $order_count; ?></h3>
            <p class="text-xs text-slate-400 mt-2">Total Orders Placed</p>
        </div>

        <!-- Referrals Card -->
        <div class="bg-slate-900/60 backdrop-blur-xl border border-slate-700/50 rounded-2xl p-6 hover:border-purple-500/30 transition-all duration-300 group">
            <div class="flex justify-between items-start mb-4">
                <div class="w-10 h-10 rounded-lg bg-purple-500/10 flex items-center justify-center border border-purple-500/20 text-purple-400 group-hover:scale-110 transition">
                    <i class="fas fa-users"></i>
                </div>
                <a href="index.php?module=user&page=referrals" class="text-xs text-purple-400 hover:text-purple-300 transition">View All <i class="fas fa-chevron-right text-[10px]"></i></a>
            </div>
            <h3 class="text-3xl font-bold text-white"><?php echo $referral_count; ?></h3>
            <p class="text-xs text-slate-400 mt-2">Active Referrals</p>
        </div>
    </div>

    <!-- Recent Orders & Quick Actions -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Recent Orders Table -->
        <div class="lg:col-span-2 bg-slate-900/60 backdrop-blur-xl border border-slate-700/50 rounded-2xl overflow-hidden">
            <div class="p-5 border-b border-slate-700/50 flex justify-between items-center bg-slate-800/30">
                <h3 class="font-bold text-white flex items-center gap-2">
                    <i class="fas fa-history text-slate-400"></i> Recent Orders
                </h3>
                <a href="index.php?module=user&page=orders" class="text-sm text-blue-400 hover:text-blue-300 transition">View All</a>
            </div>
            
            <div class="overflow-x-auto">
                <?php if (empty($recent_orders)): ?>
                    <div class="text-center py-12">
                        <div class="w-16 h-16 bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i class="fas fa-ghost text-2xl text-slate-500"></i>
                        </div>
                        <p class="text-slate-400 text-sm">No recent orders found.</p>
                        <a href="index.php?module=shop&page=category" class="inline-block mt-3 text-sm text-[#00f0ff] hover:underline">Start shopping</a>
                    </div>
                <?php else: ?>
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-900/80 text-slate-400 text-xs uppercase font-semibold">
                            <tr>
                                <th class="p-4 pl-6">Order ID</th>
                                <th class="p-4">Product</th>
                                <th class="p-4">Status</th>
                                <th class="p-4 text-right pr-6">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/50">
                            <?php foreach($recent_orders as $order): ?>
                                <tr class="hover:bg-slate-800/50 transition cursor-pointer" onclick="window.location='index.php?module=user&page=orders&view_chat=<?php echo $order['id']; ?>'">
                                    <td class="p-4 pl-6 font-mono text-slate-300">#<?php echo $order['id']; ?></td>
                                    
                                    <!-- FIXED: Uses $order['name'] instead of $order['title'] -->
                                    <td class="p-4 text-white font-medium truncate max-w-[200px]"><?php echo htmlspecialchars($order['name']); ?></td>
                                    
                                    <td class="p-4">
                                        <?php 
                                            $statusColor = match($order['status']) {
                                                'completed', 'active' => 'bg-green-500/10 text-green-400 border-green-500/20',
                                                'pending' => 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20',
                                                'cancelled', 'rejected' => 'bg-red-500/10 text-red-400 border-red-500/20',
                                                default => 'bg-slate-500/10 text-slate-400 border-slate-500/20'
                                            };
                                        ?>
                                        <span class="px-2.5 py-1 rounded-md text-[10px] font-bold uppercase border <?php echo $statusColor; ?>">
                                            <?php echo $order['status']; ?>
                                        </span>
                                    </td>
                                    
                                    <!-- FIXED: Uses $order['total_price_paid'] instead of $order['total_amount'] -->
                                    <td class="p-4 text-right text-slate-300 font-mono pr-6">
                                        <?php echo number_format($order['total_price_paid']); ?> Ks
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Links / Actions -->
        <div class="space-y-4">
            <a href="index.php?module=user&page=profile" class="flex items-center justify-between p-4 bg-slate-900/60 backdrop-blur-xl border border-slate-700/50 rounded-2xl hover:bg-slate-800 transition group">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-lg bg-slate-800 flex items-center justify-center text-slate-300 group-hover:text-white transition">
                        <i class="fas fa-user-cog"></i>
                    </div>
                    <div>
                        <h4 class="text-white font-medium text-sm">Account Settings</h4>
                        <p class="text-xs text-slate-500">Update password & details</p>
                    </div>
                </div>
                <i class="fas fa-chevron-right text-slate-600 group-hover:text-[#00f0ff] transition"></i>
            </a>

            <a href="index.php?module=user&page=wishlist" class="flex items-center justify-between p-4 bg-slate-900/60 backdrop-blur-xl border border-slate-700/50 rounded-2xl hover:bg-slate-800 transition group">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-lg bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400 group-hover:scale-110 transition">
                        <i class="fas fa-heart"></i>
                    </div>
                    <div>
                        <h4 class="text-white font-medium text-sm">My Wishlist</h4>
                        <p class="text-xs text-slate-500">Saved premium items</p>
                    </div>
                </div>
                <i class="fas fa-chevron-right text-slate-600 group-hover:text-rose-400 transition"></i>
            </a>

            <a href="index.php?module=info&page=support" class="flex items-center justify-between p-4 bg-slate-900/60 backdrop-blur-xl border border-slate-700/50 rounded-2xl hover:bg-slate-800 transition group">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 group-hover:scale-110 transition">
                        <i class="fas fa-headset"></i>
                    </div>
                    <div>
                        <h4 class="text-white font-medium text-sm">Support Center</h4>
                        <p class="text-xs text-slate-500">Need help with an order?</p>
                    </div>
                </div>
                <i class="fas fa-chevron-right text-slate-600 group-hover:text-emerald-400 transition"></i>
            </a>
            
            <a href="index.php?module=auth&page=logout" class="flex items-center justify-center gap-2 p-4 mt-4 w-full bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/20 rounded-2xl transition font-medium text-sm">
                <i class="fas fa-sign-out-alt"></i> Secure Logout
            </a>
        </div>

    </div>
</div>