<?php
// admin/user_detail.php

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 1. Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Toggle Verification Status
    if (isset($_POST['toggle_status'])) {
        $status = $_POST['is_verified']; // 1 or 0
        $stmt = $pdo->prepare("UPDATE users SET is_verified = ? WHERE id = ?");
        $stmt->execute([$status, $user_id]);
        $success = "User status updated.";
    }

    // Admin Reset Password
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
    
    // Force refresh to show changes
    // redirect(admin_url('user_detail', ['id' => $user_id])); // Optional: Use if you want to clear POST
}

// 2. Fetch User Data
$stmt = $pdo->prepare("
    SELECT u.*, 
    (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as total_orders,
    (SELECT SUM(total_price_paid) FROM orders WHERE user_id = u.id AND status = 'active') as total_spent
    FROM users u 
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    echo "<div class='p-6 bg-red-500/20 text-red-400 rounded-xl'>User not found.</div>";
    return;
}

// 3. Fetch User Orders
$stmt = $pdo->prepare("
    SELECT o.*, p.name as product_name 
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
        <div class="bg-slate-800 p-6 rounded-xl border border-slate-700 shadow-lg text-center">
            <div class="w-24 h-24 mx-auto bg-gradient-to-br from-blue-600 to-purple-600 rounded-full flex items-center justify-center text-3xl font-bold text-white mb-4 shadow-xl">
                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
            </div>
            
            <h2 class="text-xl font-bold text-white"><?php echo htmlspecialchars($user['full_name']); ?></h2>
            <p class="text-slate-400 text-sm">@<?php echo htmlspecialchars($user['username']); ?></p>
            
            <div class="mt-6 grid grid-cols-2 gap-4 border-t border-slate-700 pt-4">
                <div>
                    <span class="block text-slate-500 text-xs uppercase font-bold">Spent</span>
                    <span class="block text-green-400 font-mono text-lg"><?php echo format_admin_currency($user['total_spent'] ?: 0); ?></span>
                </div>
                <div>
                    <span class="block text-slate-500 text-xs uppercase font-bold">Orders</span>
                    <span class="block text-white font-mono text-lg"><?php echo $user['total_orders']; ?></span>
                </div>
            </div>
        </div>

        <!-- Feedback Messages -->
        <?php if(isset($success)) echo "<div class='bg-green-500/20 text-green-400 p-3 rounded border border-green-500/50 text-sm'>$success</div>"; ?>
        <?php if(isset($error)) echo "<div class='bg-red-500/20 text-red-400 p-3 rounded border border-red-500/50 text-sm'>$error</div>"; ?>

        <!-- Edit Status -->
        <div class="bg-slate-800 p-6 rounded-xl border border-slate-700">
            <h3 class="font-bold text-white mb-4 text-sm uppercase">Account Status</h3>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Email Verification</label>
                    <select name="is_verified" class="w-full bg-slate-900 border border-slate-600 rounded p-2 text-white text-sm focus:border-blue-500 outline-none">
                        <option value="1" <?php echo $user['is_verified'] ? 'selected' : ''; ?>>Verified (Active)</option>
                        <option value="0" <?php echo !$user['is_verified'] ? 'selected' : ''; ?>>Unverified (Locked)</option>
                    </select>
                </div>
                <button type="submit" name="toggle_status" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded text-sm transition">
                    Update Status
                </button>
            </form>
        </div>

        <!-- Admin Reset Password -->
        <div class="bg-slate-800 p-6 rounded-xl border border-slate-700">
            <h3 class="font-bold text-white mb-4 text-sm uppercase">Admin Reset Password</h3>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">New Password</label>
                    <input type="text" name="new_password" placeholder="Enter new password" class="w-full bg-slate-900 border border-slate-600 rounded p-2 text-white text-sm focus:border-red-500 outline-none">
                    <p class="text-[10px] text-slate-500 mt-1">Leave blank unless user requested a reset.</p>
                </div>
                <button type="submit" name="reset_password" class="w-full bg-slate-700 hover:bg-red-600 text-white font-bold py-2 rounded text-sm transition" onclick="return confirm('Are you sure you want to change this user\'s password?')">
                    Reset Password
                </button>
            </form>
        </div>

        <!-- Contact Info -->
        <div class="bg-slate-800 p-6 rounded-xl border border-slate-700 space-y-3 text-sm">
            <h3 class="font-bold text-white mb-2 text-sm uppercase">Contact Details</h3>
            <div class="flex justify-between border-b border-slate-700 pb-2">
                <span class="text-slate-500">Email</span>
                <span class="text-blue-400 truncate max-w-[150px]" title="<?php echo htmlspecialchars($user['email']); ?>"><?php echo htmlspecialchars($user['email']); ?></span>
            </div>
            <div class="flex justify-between border-b border-slate-700 pb-2">
                <span class="text-slate-500">Phone</span>
                <span class="text-slate-300"><?php echo htmlspecialchars($user['phone'] ?: 'N/A'); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Joined</span>
                <span class="text-slate-300"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
            </div>
        </div>

    </div>

    <!-- RIGHT COLUMN: Order History -->
    <div class="lg:col-span-2">
        <div class="bg-slate-800 rounded-xl border border-slate-700 overflow-hidden shadow-lg h-full">
            <div class="p-6 border-b border-slate-700 flex justify-between items-center">
                <h3 class="font-bold text-white">Order History</h3>
                <span class="bg-slate-700 text-slate-300 text-xs px-2 py-1 rounded">Last 20</span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-700/50 text-slate-400 uppercase text-xs">
                        <tr>
                            <th class="p-4 pl-6">ID</th>
                            <th class="p-4">Product</th>
                            <th class="p-4">Amount</th>
                            <th class="p-4">Status</th>
                            <th class="p-4">Date</th>
                            <th class="p-4 text-right pr-6">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        <?php foreach($orders as $o): ?>
                        <tr class="hover:bg-slate-700/30 transition group">
                            <td class="p-4 pl-6 font-mono text-slate-500">#<?php echo $o['id']; ?></td>
                            <td class="p-4 font-medium text-white"><?php echo htmlspecialchars($o['product_name']); ?></td>
                            <td class="p-4 font-mono text-green-400"><?php echo format_admin_currency($o['total_price_paid']); ?></td>
                            <td class="p-4"><?php echo format_status_badge($o['status']); ?></td>
                            <td class="p-4 text-slate-500 text-xs"><?php echo date('M d, Y', strtotime($o['created_at'])); ?></td>
                            <td class="p-4 text-right pr-6">
                                <a href="<?php echo admin_url('order_detail', ['id' => $o['id']]); ?>" class="text-blue-400 hover:text-white text-xs border border-blue-500/30 hover:bg-blue-600 px-3 py-1 rounded transition">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($orders)): ?>
                            <tr><td colspan="6" class="p-8 text-center text-slate-500 italic">No orders found for this user.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>