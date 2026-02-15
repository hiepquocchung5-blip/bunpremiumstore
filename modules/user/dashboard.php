<?php
// modules/user/dashboard.php

// 1. Auth Guard
if (!is_logged_in()) redirect('index.php?module=auth&page=login');

$user_id = $_SESSION['user_id'];

// 2. Fetch User Stats
$stmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM orders WHERE user_id = ?) as total_orders,
        (SELECT SUM(total_price_paid) FROM orders WHERE user_id = ? AND status = 'active') as total_spent,
        (SELECT COUNT(*) FROM wishlist WHERE user_id = ?) as wishlist_count,
        (SELECT COUNT(*) FROM reviews WHERE user_id = ?) as reviews_count
    FROM DUAL
");
$stmt->execute([$user_id, $user_id, $user_id, $user_id]);
$stats = $stmt->fetch();

// 3. Fetch Active Agent Pass
$stmt = $pdo->prepare("
    SELECT p.name, p.discount_percent, up.expires_at 
    FROM user_passes up 
    JOIN passes p ON up.pass_id = p.id 
    WHERE up.user_id = ? AND up.status = 'active' AND up.expires_at > NOW() 
    LIMIT 1
");
$stmt->execute([$user_id]);
$agent = $stmt->fetch();

// 4. Fetch Recent Orders
$stmt = $pdo->prepare("
    SELECT o.*, p.name as product_name, p.image_path
    FROM orders o 
    JOIN products p ON o.product_id = p.id 
    WHERE o.user_id = ? 
    ORDER BY o.created_at DESC LIMIT 3
");
$stmt->execute([$user_id]);
$recent_orders = $stmt->fetchAll();
?>

<div class="max-w-6xl mx-auto space-y-8">
    
    <!-- Welcome Section -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-3xl font-bold text-white">My Dashboard</h1>
            <p class="text-gray-400 text-sm mt-1">Welcome back, <span class="text-blue-400 font-semibold"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span></p>
        </div>
        
        <?php if($agent): ?>
            <div class="glass px-5 py-2 rounded-xl border border-yellow-500/30 flex items-center gap-3 shadow-lg shadow-yellow-900/20">
                <div class="w-10 h-10 bg-yellow-500/20 rounded-full flex items-center justify-center text-yellow-400 border border-yellow-500/20">
                    <i class="fas fa-crown"></i>
                </div>
                <div>
                    <p class="text-xs text-yellow-500 font-bold uppercase tracking-wider"><?php echo htmlspecialchars($agent['name']); ?></p>
                    <p class="text-[10px] text-gray-400"><?php echo $agent['discount_percent']; ?>% Discount • Exp: <?php echo date('M d', strtotime($agent['expires_at'])); ?></p>
                </div>
            </div>
        <?php else: ?>
            <a href="index.php?module=user&page=agent" class="group glass px-5 py-2.5 rounded-xl border border-gray-700 hover:border-blue-500/50 flex items-center gap-3 transition">
                <div class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center text-gray-400 group-hover:text-blue-400 transition">
                    <i class="far fa-star"></i>
                </div>
                <div class="text-left">
                    <p class="text-xs text-white font-bold">Standard Account</p>
                    <p class="text-[10px] text-blue-400 group-hover:underline">Upgrade to Agent &rarr;</p>
                </div>
            </a>
        <?php endif; ?>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="glass p-5 rounded-2xl border border-gray-700/50">
            <p class="text-gray-400 text-xs font-bold uppercase tracking-wider mb-1">Total Spent</p>
            <p class="text-2xl font-bold text-green-400"><?php echo format_price($stats['total_spent'] ?: 0); ?></p>
        </div>
        <div class="glass p-5 rounded-2xl border border-gray-700/50">
            <p class="text-gray-400 text-xs font-bold uppercase tracking-wider mb-1">Total Orders</p>
            <p class="text-2xl font-bold text-white"><?php echo $stats['total_orders']; ?></p>
        </div>
        <div class="glass p-5 rounded-2xl border border-gray-700/50">
            <p class="text-gray-400 text-xs font-bold uppercase tracking-wider mb-1">Wishlist</p>
            <p class="text-2xl font-bold text-pink-400"><?php echo $stats['wishlist_count']; ?></p>
        </div>
        <div class="glass p-5 rounded-2xl border border-gray-700/50">
            <p class="text-gray-400 text-xs font-bold uppercase tracking-wider mb-1">Reviews</p>
            <p class="text-2xl font-bold text-yellow-400"><?php echo $stats['reviews_count']; ?></p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Left: Recent Orders -->
        <div class="lg:col-span-2 glass rounded-2xl border border-gray-700 overflow-hidden">
            <div class="p-6 border-b border-gray-700/50 flex justify-between items-center">
                <h3 class="font-bold text-white text-lg">Recent Orders</h3>
                <a href="index.php?module=user&page=orders" class="text-xs text-blue-400 hover:text-white transition">View All</a>
            </div>
            
            <div class="p-2">
                <?php if(empty($recent_orders)): ?>
                    <div class="text-center py-10 text-gray-500">
                        <i class="fas fa-box-open text-4xl mb-2 opacity-50"></i>
                        <p class="text-sm">No recent activity.</p>
                        <a href="index.php" class="text-blue-400 text-xs hover:underline mt-2 inline-block">Start Shopping</a>
                    </div>
                <?php else: ?>
                    <?php foreach($recent_orders as $order): ?>
                        <a href="index.php?module=user&page=orders&view_chat=<?php echo $order['id']; ?>" class="block p-4 rounded-xl hover:bg-gray-800/50 transition border border-transparent hover:border-gray-700 group">
                            <div class="flex items-center gap-4">
                                <!-- Product Icon/Image -->
                                <div class="w-12 h-12 bg-gray-900 rounded-lg flex items-center justify-center border border-gray-700 shrink-0">
                                    <?php if($order['image_path']): ?>
                                        <img src="<?php echo BASE_URL . $order['image_path']; ?>" class="w-full h-full object-cover rounded-lg">
                                    <?php else: ?>
                                        <i class="fas fa-cube text-gray-500 group-hover:text-blue-500 transition"></i>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex-1 min-w-0">
                                    <div class="flex justify-between mb-1">
                                        <h4 class="text-sm font-bold text-white truncate"><?php echo htmlspecialchars($order['product_name']); ?></h4>
                                        <span class="text-xs text-gray-500"><?php echo date('M d', strtotime($order['created_at'])); ?></span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <p class="text-xs text-gray-400">Order #<?php echo $order['id']; ?></p>
                                        
                                        <!-- Status Badge -->
                                        <?php if($order['status'] == 'active'): ?>
                                            <span class="text-[10px] font-bold text-green-400 bg-green-500/10 px-2 py-0.5 rounded border border-green-500/20">Active</span>
                                        <?php elseif($order['status'] == 'pending'): ?>
                                            <span class="text-[10px] font-bold text-yellow-400 bg-yellow-500/10 px-2 py-0.5 rounded border border-yellow-500/20">Pending</span>
                                        <?php else: ?>
                                            <span class="text-[10px] font-bold text-red-400 bg-red-500/10 px-2 py-0.5 rounded border border-red-500/20">Rejected</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Quick Actions -->
        <div class="space-y-6">
            <!-- Shortcuts -->
            <div class="glass p-6 rounded-2xl border border-gray-700">
                <h3 class="font-bold text-white text-sm uppercase tracking-wider mb-4">Quick Actions</h3>
                <div class="space-y-2">
                    <a href="index.php?module=user&page=profile" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-800 transition text-gray-300 hover:text-white">
                        <div class="w-8 h-8 rounded-full bg-blue-500/20 flex items-center justify-center text-blue-400"><i class="fas fa-user-cog text-xs"></i></div>
                        <span class="text-sm font-medium">Edit Profile</span>
                    </a>
                    <a href="index.php?module=user&page=wishlist" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-800 transition text-gray-300 hover:text-white">
                        <div class="w-8 h-8 rounded-full bg-pink-500/20 flex items-center justify-center text-pink-400"><i class="fas fa-heart text-xs"></i></div>
                        <span class="text-sm font-medium">My Wishlist</span>
                    </a>
                    <a href="index.php?module=info&page=support" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-800 transition text-gray-300 hover:text-white">
                        <div class="w-8 h-8 rounded-full bg-purple-500/20 flex items-center justify-center text-purple-400"><i class="fas fa-headset text-xs"></i></div>
                        <span class="text-sm font-medium">Contact Support</span>
                    </a>
                </div>
            </div>

            <!-- Support Card -->
            <div class="bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl p-6 text-center shadow-lg relative overflow-hidden group">
                <div class="absolute -right-4 -top-4 w-24 h-24 bg-white/10 rounded-full blur-2xl group-hover:bg-white/20 transition"></div>
                <i class="fab fa-telegram-plane text-4xl text-white mb-3 relative z-10"></i>
                <h3 class="font-bold text-white relative z-10">Need Help?</h3>
                <p class="text-blue-100 text-xs mb-4 relative z-10">Chat with our support team instantly.</p>
                <a href="https://t.me/bunpremiumstore" target="_blank" class="inline-block bg-white text-blue-600 px-6 py-2 rounded-full font-bold text-sm shadow hover:bg-gray-100 transition relative z-10">
                    Open Telegram
                </a>
            </div>
        </div>

    </div>
</div>