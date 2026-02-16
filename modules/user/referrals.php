<?php
// modules/user/referrals.php

if (!is_logged_in()) redirect('index.php?module=auth&page=login');

$user_id = $_SESSION['user_id'];

// 1. Generate Code if missing
$stmt = $pdo->prepare("SELECT referral_code, wallet_balance FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();

if (empty($user_data['referral_code'])) {
    $new_code = 'REF' . $user_id . strtoupper(substr(md5(uniqid()), 0, 5));
    $pdo->prepare("UPDATE users SET referral_code = ? WHERE id = ?")->execute([$new_code, $user_id]);
    $user_data['referral_code'] = $new_code;
    // Refresh page to show code
    redirect('index.php?module=user&page=referrals');
}

$ref_link = BASE_URL . "index.php?module=auth&page=register&ref=" . $user_data['referral_code'];

// 2. Fetch Referrals
$stmt = $pdo->prepare("
    SELECT id, username, created_at, 
    (SELECT COUNT(*) FROM orders WHERE user_id = users.id AND status = 'active') as order_count
    FROM users 
    WHERE referred_by = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$user_id]);
$referrals = $stmt->fetchAll();

// 3. Fetch Wallet History
$stmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$user_id]);
$transactions = $stmt->fetchAll();
?>

<div class="max-w-6xl mx-auto space-y-8">
    
    <!-- Hero / Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Balance Card -->
        <div class="glass p-6 rounded-2xl border border-gray-700 relative overflow-hidden group">
            <div class="absolute right-0 top-0 p-4 opacity-5 group-hover:scale-110 transition"><i class="fas fa-wallet text-6xl text-green-500"></i></div>
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Wallet Balance</p>
            <h2 class="text-3xl font-bold text-white mt-1"><?php echo format_price($user_data['wallet_balance']); ?></h2>
            <p class="text-green-400 text-xs mt-2 flex items-center gap-1"><i class="fas fa-arrow-up"></i> Earnings available</p>
        </div>

        <!-- Referral Link -->
        <div class="md:col-span-2 glass p-6 rounded-2xl border border-gray-700 flex flex-col justify-center">
            <h3 class="font-bold text-white mb-2 flex items-center gap-2">
                <i class="fas fa-link text-blue-500"></i> Your Referral Link
            </h3>
            <p class="text-xs text-gray-400 mb-4">Share this link. Earn 500 Ks for every friend who makes their first purchase!</p>
            
            <div class="flex gap-2">
                <input type="text" value="<?php echo $ref_link; ?>" readonly class="flex-1 bg-gray-900 border border-gray-600 rounded-lg px-4 py-2 text-sm text-gray-300 focus:border-blue-500 outline-none select-all font-mono">
                <button onclick="navigator.clipboard.writeText('<?php echo $ref_link; ?>'); alert('Link Copied!');" class="bg-blue-600 hover:bg-blue-500 text-white px-6 py-2 rounded-lg font-bold text-sm transition shadow-lg">
                    Copy
                </button>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <!-- Referral List -->
        <div class="glass rounded-2xl border border-gray-700 overflow-hidden">
            <div class="p-6 border-b border-gray-700/50 flex justify-between items-center bg-gray-800/30">
                <h3 class="font-bold text-white">Referred Users</h3>
                <span class="bg-gray-800 text-xs px-2 py-1 rounded text-gray-400"><?php echo count($referrals); ?> Total</span>
            </div>
            
            <div class="max-h-96 overflow-y-auto custom-scrollbar">
                <?php if(empty($referrals)): ?>
                    <div class="text-center py-10 text-gray-500">
                        <i class="fas fa-users text-3xl mb-2 opacity-30"></i>
                        <p class="text-xs">No referrals yet.</p>
                    </div>
                <?php else: ?>
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-900/50 text-gray-500 text-xs uppercase">
                            <tr>
                                <th class="p-4 pl-6">User</th>
                                <th class="p-4 text-center">Orders</th>
                                <th class="p-4 text-right pr-6">Joined</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700/50">
                            <?php foreach($referrals as $ref): ?>
                                <tr class="hover:bg-gray-800/50 transition">
                                    <td class="p-4 pl-6 font-medium text-white"><?php echo htmlspecialchars($ref['username']); ?></td>
                                    <td class="p-4 text-center">
                                        <span class="px-2 py-1 rounded text-xs <?php echo $ref['order_count'] > 0 ? 'bg-green-500/20 text-green-400' : 'bg-gray-700 text-gray-400'; ?>">
                                            <?php echo $ref['order_count']; ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-right text-gray-500 text-xs pr-6"><?php echo date('M d, Y', strtotime($ref['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Wallet History -->
        <div class="glass rounded-2xl border border-gray-700 overflow-hidden">
            <div class="p-6 border-b border-gray-700/50 flex justify-between items-center bg-gray-800/30">
                <h3 class="font-bold text-white">Wallet History</h3>
            </div>
            
            <div class="max-h-96 overflow-y-auto custom-scrollbar">
                <?php if(empty($transactions)): ?>
                    <div class="text-center py-10 text-gray-500">
                        <i class="fas fa-history text-3xl mb-2 opacity-30"></i>
                        <p class="text-xs">No transactions yet.</p>
                    </div>
                <?php else: ?>
                    <div class="divide-y divide-gray-700/50">
                        <?php foreach($transactions as $txn): ?>
                            <div class="p-4 flex justify-between items-center hover:bg-gray-800/50 transition">
                                <div>
                                    <p class="text-sm text-gray-200 font-medium"><?php echo htmlspecialchars($txn['description']); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo date('M d, H:i', strtotime($txn['created_at'])); ?></p>
                                </div>
                                <span class="font-mono font-bold <?php echo $txn['type'] == 'credit' ? 'text-green-400' : 'text-red-400'; ?>">
                                    <?php echo ($txn['type'] == 'credit' ? '+' : '-') . format_price($txn['amount']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>