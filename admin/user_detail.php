<?php
// admin/user_detail.php

// Include Push Service
require_once dirname(__DIR__) . '/includes/PushService.php';

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 1. Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Action: Update Verification Status
    if (isset($_POST['toggle_status'])) {
        $status = $_POST['is_verified']; // 1 or 0
        $stmt = $pdo->prepare("UPDATE users SET is_verified = ? WHERE id = ?");
        $stmt->execute([$status, $user_id]);
        $success = "User status updated.";
    }

    // Action: Reset Password
    if (isset($_POST['reset_password'])) {
        $new_pass = $_POST['new_password'];
        if (strlen($new_pass) >= 6) {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hash, $user_id]);
            $success = "Password reset successfully.";
        } else {
            $error = "Password must be at least 6 characters.";
        }
    }

    // Action: Send Custom Push Notification
    if (isset($_POST['send_push'])) {
        $title = trim($_POST['push_title']);
        $body = trim($_POST['push_body']);
        $url = trim($_POST['push_url']);

        if ($title && $body) {
            $push = new PushService($pdo);
            if ($push->sendToUser($user_id, $title, $body, $url)) {
                $success = "Push notification sent to user devices.";
            } else {
                $error = "Failed to send. User might not have enabled notifications.";
            }
        } else {
            $error = "Title and Body are required for notifications.";
        }
    }
}

// 2. Fetch User Data
$stmt = $pdo->prepare("
    SELECT u.*, 
    (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as total_orders,
    (SELECT SUM(total_price_paid) FROM orders WHERE user_id = u.id AND status = 'active') as total_spent,
    (SELECT COUNT(*) FROM push_subscriptions WHERE user_id = u.id) as device_count
    FROM users u 
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    echo "<div class='p-6 bg-red-500/20 text-red-400 rounded-xl border border-red-500/50'>User not found. <a href='".admin_url('users')."' class='underline'>Back</a></div>";
    return;
}

// 3. Fetch User Orders
$stmt = $pdo->prepare("
    SELECT o.*, p.name as product_name, p.delivery_type 
    FROM orders o 
    JOIN products p ON o.product_id = p.id 
    WHERE o.user_id = ? 
    ORDER BY o.created_at DESC LIMIT 20
");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll();
?>

<div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    <!-- LEFT COLUMN: Profile & Actions -->
    <div class="lg:col-span-1 space-y-6">
        
        <!-- Profile Card -->
        <div class="bg-slate-800 p-6 rounded-xl border border-slate-700 shadow-lg text-center relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-blue-600/10 to-purple-600/10 pointer-events-none"></div>
            
            <div class="relative z-10">
                <div class="w-24 h-24 mx-auto bg-gradient-to-br from-blue-600 to-purple-600 rounded-full flex items-center justify-center text-3xl font-bold text-white mb-4 shadow-xl border-4 border-slate-800">
                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                </div>
                
                <h2 class="text-xl font-bold text-white"><?php echo htmlspecialchars($user['full_name']); ?></h2>
                <p class="text-slate-400 text-sm mb-4">@<?php echo htmlspecialchars($user['username']); ?></p>
                
                <div class="flex justify-center gap-2 mb-6">
                    <span class="px-2 py-1 rounded text-xs font-bold <?php echo $user['is_verified'] ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'; ?>">
                        <?php echo $user['is_verified'] ? 'Verified' : 'Unverified'; ?>
                    </span>
                    <span class="px-2 py-1 rounded text-xs font-bold bg-slate-700 text-slate-300">
                        ID: #<?php echo $user['id']; ?>
                    </span>
                </div>

                <div class="grid grid-cols-3 gap-2 border-t border-slate-700 pt-4 text-center">
                    <div>
                        <span class="block text-slate-500 text-[10px] uppercase font-bold tracking-wider">Spent</span>
                        <span class="block text-green-400 font-mono text-sm font-bold"><?php echo format_admin_currency($user['total_spent'] ?: 0); ?></span>
                    </div>
                    <div>
                        <span class="block text-slate-500 text-[10px] uppercase font-bold tracking-wider">Orders</span>
                        <span class="block text-white font-mono text-sm font-bold"><?php echo $user['total_orders']; ?></span>
                    </div>
                    <div>
                        <span class="block text-slate-500 text-[10px] uppercase font-bold tracking-wider">Devices</span>
                        <span class="block text-blue-400 font-mono text-sm font-bold"><i class="fas fa-mobile-alt mr-1"></i><?php echo $user['device_count']; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Feedback Messages -->
        <?php if(isset($success)) echo "<div class='bg-green-500/20 text-green-400 p-3 rounded-lg border border-green-500/50 text-sm flex items-center gap-2'><i class='fas fa-check-circle'></i> $success</div>"; ?>
        <?php if(isset($error)) echo "<div class='bg-red-500/20 text-red-400 p-3 rounded-lg border border-red-500/50 text-sm flex items-center gap-2'><i class='fas fa-exclamation-triangle'></i> $error</div>"; ?>

        <!-- Send Push Notification -->
        <div class="bg-slate-800 p-6 rounded-xl border border-slate-700 shadow-lg">
            <h3 class="font-bold text-white mb-4 text-sm uppercase flex items-center gap-2">
                <i class="fas fa-bell text-blue-500"></i> Send Push Alert
            </h3>
            <form method="POST" class="space-y-3">
                <div>
                    <input type="text" name="push_title" placeholder="Title (e.g. Special Offer)" class="w-full bg-slate-900 border border-slate-600 rounded p-2 text-white text-xs focus:border-blue-500 outline-none">
                </div>
                <div>
                    <textarea name="push_body" rows="2" placeholder="Message body..." class="w-full bg-slate-900 border border-slate-600 rounded p-2 text-white text-xs focus:border-blue-500 outline-none resize-none"></textarea>
                </div>
                <div>
                    <input type="text" name="push_url" placeholder="URL (Optional)" class="w-full bg-slate-900 border border-slate-600 rounded p-2 text-white text-xs focus:border-blue-500 outline-none">
                </div>
                <button type="submit" name="send_push" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded text-xs transition <?php echo $user['device_count'] == 0 ? 'opacity-50 cursor-not-allowed' : ''; ?>" <?php echo $user['device_count'] == 0 ? 'disabled' : ''; ?>>
                    <?php echo $user['device_count'] > 0 ? 'Send Notification' : 'User Not Subscribed'; ?>
                </button>
            </form>
        </div>

        <!-- Edit Status -->
        <div class="bg-slate-800 p-6 rounded-xl border border-slate-700">
            <h3 class="font-bold text-white mb-4 text-sm uppercase flex items-center gap-2">
                <i class="fas fa-user-cog text-gray-400"></i> Account Status
            </h3>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Email Verification</label>
                    <select name="is_verified" class="w-full bg-slate-900 border border-slate-600 rounded-lg p-2.5 text-white text-sm focus:border-blue-500 outline-none">
                        <option value="1" <?php echo $user['is_verified'] ? 'selected' : ''; ?>>Verified (Active)</option>
                        <option value="0" <?php echo !$user['is_verified'] ? 'selected' : ''; ?>>Unverified (Locked)</option>
                    </select>
                </div>
                <button type="submit" name="toggle_status" class="w-full bg-slate-700 hover:bg-slate-600 text-white font-bold py-2 rounded-lg text-sm transition">
                    Update Status
                </button>
            </form>
        </div>

        <!-- Admin Reset Password -->
        <div class="bg-slate-800 p-6 rounded-xl border border-slate-700">
            <h3 class="font-bold text-white mb-4 text-sm uppercase flex items-center gap-2">
                <i class="fas fa-key text-red-500"></i> Force Reset Password
            </h3>
            <form method="POST" class="space-y-4">
                <div>
                    <input type="text" name="new_password" placeholder="Enter new password" class="w-full bg-slate-900 border border-slate-600 rounded-lg p-2.5 text-white text-sm focus:border-red-500 outline-none">
                </div>
                <button type="submit" name="reset_password" class="w-full bg-red-600/20 text-red-400 hover:bg-red-600 hover:text-white border border-red-600/50 font-bold py-2 rounded-lg text-sm transition" onclick="return confirm('Are you sure you want to change this user\'s password?')">
                    Reset Password
                </button>
            </form>
        </div>

    </div>

    <!-- RIGHT COLUMN: Order History -->
    <div class="lg:col-span-2">
        <div class="bg-slate-800 rounded-xl border border-slate-700 overflow-hidden shadow-lg h-full flex flex-col">
            <div class="p-6 border-b border-slate-700 flex justify-between items-center bg-slate-800/50 backdrop-blur shrink-0">
                <h3 class="font-bold text-white text-lg">Order History</h3>
                <span class="bg-slate-700 text-slate-300 text-xs px-2.5 py-1 rounded-full font-medium">Last 20</span>
            </div>
            
            <div class="overflow-x-auto flex-grow custom-scrollbar">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-900/50 text-slate-400 uppercase text-xs font-semibold sticky top-0">
                        <tr>
                            <th class="p-4 pl-6">ID</th>
                            <th class="p-4">Product</th>
                            <th class="p-4 text-right">Amount</th>
                            <th class="p-4 text-center">Status</th>
                            <th class="p-4 text-right">Date</th>
                            <th class="p-4 text-right pr-6">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700">
                        <?php foreach($orders as $o): ?>
                        <tr class="hover:bg-slate-700/30 transition group">
                            <td class="p-4 pl-6 font-mono text-slate-500 group-hover:text-slate-300 transition">#<?php echo $o['id']; ?></td>
                            <td class="p-4">
                                <div class="font-medium text-white"><?php echo htmlspecialchars($o['product_name']); ?></div>
                                <div class="text-[10px] text-slate-500 uppercase"><?php echo $o['delivery_type']; ?></div>
                            </td>
                            <td class="p-4 text-right font-mono text-green-400 font-bold"><?php echo format_admin_currency($o['total_price_paid']); ?></td>
                            <td class="p-4 text-center"><?php echo format_status_badge($o['status']); ?></td>
                            <td class="p-4 text-right text-slate-500 text-xs"><?php echo date('M d, Y', strtotime($o['created_at'])); ?></td>
                            <td class="p-4 text-right pr-6">
                                <a href="<?php echo admin_url('order_detail', ['id' => $o['id']]); ?>" class="text-slate-400 hover:text-white text-xs border border-slate-600 hover:bg-slate-600 px-3 py-1.5 rounded transition font-medium">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($orders)): ?>
                            <tr><td colspan="6" class="p-10 text-center text-slate-500 italic">No orders found for this user.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>