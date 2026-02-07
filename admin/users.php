<?php
// admin/users.php

// 1. Handle Delete User
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Prevent deleting active orders (optional safety check)
    // For now, we will delete user and CASCADE will handle orders if set in DB, 
    // or we assume admin knows what they are doing.
    
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    if ($stmt->execute([$id])) {
        redirect(admin_url('users', ['msg' => 'deleted']));
    }
}

// 2. Fetch Users with Stats
$users = $pdo->query("
    SELECT u.*, 
    (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as total_orders,
    (SELECT SUM(total_price_paid) FROM orders WHERE user_id = u.id AND status = 'active') as total_spent,
    (SELECT name FROM passes JOIN user_passes ON passes.id = user_passes.pass_id WHERE user_passes.user_id = u.id AND user_passes.status = 'active' LIMIT 1) as active_pass
    FROM users u 
    ORDER BY u.created_at DESC
")->fetchAll();
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-3xl font-bold text-white">User Management</h1>
        <p class="text-slate-400 text-sm mt-1">Total Registered: <?php echo count($users); ?></p>
    </div>
</div>

<?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
    <div class="bg-red-500/20 text-red-400 p-4 rounded-xl border border-red-500/50 mb-6 flex items-center gap-3">
        <i class="fas fa-trash"></i> User deleted successfully.
    </div>
<?php endif; ?>

<div class="bg-slate-800 rounded-xl border border-slate-700 overflow-hidden shadow-lg">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-700/50 text-slate-400 uppercase text-xs">
                <tr>
                    <th class="p-4">ID</th>
                    <th class="p-4">User</th>
                    <th class="p-4">Contact</th>
                    <th class="p-4">Membership</th>
                    <th class="p-4 text-center">Orders</th>
                    <th class="p-4 text-right">Total Spent</th>
                    <th class="p-4 text-right">Joined</th>
                    <th class="p-4 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700">
                <?php foreach($users as $u): ?>
                    <tr class="hover:bg-slate-700/30 transition group">
                        <td class="p-4 text-slate-500">#<?php echo $u['id']; ?></td>
                        <td class="p-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-600 to-purple-600 flex items-center justify-center text-xs font-bold text-white shadow-lg">
                                    <?php echo strtoupper(substr($u['username'], 0, 2)); ?>
                                </div>
                                <div>
                                    <div class="font-bold text-white"><?php echo htmlspecialchars($u['full_name']); ?></div>
                                    <div class="text-xs text-slate-400">@<?php echo htmlspecialchars($u['username']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="p-4">
                            <div class="text-slate-300 text-xs flex items-center gap-1">
                                <i class="fas fa-envelope text-slate-500"></i> 
                                <?php echo htmlspecialchars($u['email']); ?>
                                <?php if(isset($u['is_verified']) && $u['is_verified']): ?>
                                    <span class="text-green-400" title="Email Verified"><i class="fas fa-check-circle text-[10px]"></i></span>
                                <?php endif; ?>
                            </div>
                            <div class="text-slate-500 text-xs mt-1">
                                <?php echo htmlspecialchars($u['phone'] ?: '-'); ?>
                            </div>
                        </td>
                        <td class="p-4">
                            <?php if($u['active_pass']): ?>
                                <span class="bg-yellow-500/20 text-yellow-400 px-2 py-1 rounded text-xs font-bold border border-yellow-500/30 shadow-sm">
                                    <i class="fas fa-crown mr-1"></i> <?php echo htmlspecialchars($u['active_pass']); ?>
                                </span>
                            <?php else: ?>
                                <span class="text-slate-600 text-xs bg-slate-900 px-2 py-1 rounded">Standard</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-4 text-center">
                            <span class="bg-slate-900 text-slate-300 px-2.5 py-1 rounded font-mono text-xs border border-slate-700">
                                <?php echo $u['total_orders']; ?>
                            </span>
                        </td>
                        <td class="p-4 text-right font-mono text-green-400 font-bold">
                            <?php echo format_admin_currency($u['total_spent'] ?: 0); ?>
                        </td>
                        <td class="p-4 text-right text-slate-500 text-xs">
                            <?php echo date('M d, Y', strtotime($u['created_at'])); ?>
                        </td>
                        <td class="p-4 text-right">
                            <a href="<?php echo admin_url('users', ['delete' => $u['id']]); ?>" 
                               class="text-slate-500 hover:text-red-400 transition p-2 rounded hover:bg-slate-700" 
                               onclick="return confirm('Are you sure? This will delete the user and may affect their order history.')"
                               title="Delete User">
                                <i class="fas fa-user-times"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if(empty($users)): ?>
                    <tr><td colspan="8" class="p-8 text-center text-slate-500">No users found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>