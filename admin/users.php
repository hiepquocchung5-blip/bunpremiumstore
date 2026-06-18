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

<div class="mb-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-6 animate-fade-in">
    <div>
        <h1 class="text-3xl md:text-5xl font-extrabold text-white tracking-tight font-heading">Customer Base <span class="text-indigo-500">.</span></h1>
        <p class="text-slate-500 text-sm mt-3 leading-relaxed">Currently managing <span class="text-indigo-400 font-bold"><?php echo count($users); ?></span> registered members in the system.</p>
    </div>
</div>

<?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
    <div class="bg-rose-500/10 border border-rose-500/20 text-rose-400 p-4 rounded-2xl mb-8 flex items-center gap-3 animate-fade-in shadow-sm">
        <div class="w-8 h-8 rounded-lg bg-rose-500/20 flex items-center justify-center">
            <i class="fas fa-user-minus"></i>
        </div>
        <span class="text-sm font-bold">User account has been successfully purged from the database.</span>
    </div>
<?php endif; ?>

<div class="custom-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm whitespace-nowrap">
            <thead>
                <tr class="bg-black/20 text-slate-500 uppercase text-[10px] tracking-[0.2em] font-bold">
                    <th class="p-6 pl-10">System ID</th>
                    <th class="p-6">Client Profile</th>
                    <th class="p-6">Communication</th>
                    <th class="p-6">Account Tier</th>
                    <th class="p-6 text-center">Volume</th>
                    <th class="p-6 text-right">LTV (Lifetime Value)</th>
                    <th class="p-6 text-right">Registered</th>
                    <th class="p-6 pr-10 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/[0.03]">
                <?php foreach($users as $u): ?>
                    <tr class="hover:bg-indigo-500/[0.02] transition-colors group">
                        <td class="p-6 pl-10">
                            <span class="bg-slate-800/50 text-slate-400 px-3 py-1.5 rounded-xl font-mono text-[10px] border border-white/5">#<?php echo $u['id']; ?></span>
                        </td>
                        <td class="p-6">
                            <div class="flex items-center gap-4">
                                <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-sm font-bold text-white shadow-lg group-hover:scale-105 transition-transform">
                                    <?php echo strtoupper(substr($u['username'], 0, 1)); ?>
                                </div>
                                <div class="flex flex-col">
                                    <span class="font-bold text-white group-hover:text-indigo-400 transition-colors"><?php echo htmlspecialchars($u['full_name']); ?></span>
                                    <span class="text-[10px] text-slate-500 font-bold uppercase tracking-widest mt-0.5">@<?php echo htmlspecialchars($u['username']); ?></span>
                                </div>
                            </div>
                        </td>
                        <td class="p-6">
                            <div class="flex flex-col gap-1.5">
                                <div class="text-[12px] text-slate-300 flex items-center gap-2">
                                    <i class="far fa-envelope text-indigo-400 text-[10px]"></i> 
                                    <?php echo htmlspecialchars($u['email']); ?>
                                    <?php if(isset($u['is_verified']) && $u['is_verified']): ?>
                                        <span class="text-emerald-400" title="Email Verified"><i class="fas fa-circle-check text-[9px]"></i></span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-[10px] text-slate-500 font-medium flex items-center gap-2 ml-1">
                                    <i class="fas fa-phone text-[9px] opacity-50"></i>
                                    <?php echo htmlspecialchars($u['phone'] ?: 'No contact'); ?>
                                </div>
                            </div>
                        </td>
                        <td class="p-6">
                            <?php if($u['active_pass']): ?>
                                <span class="bg-amber-500/10 text-amber-400 px-3 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-widest border border-amber-500/20 flex items-center gap-2 w-fit shadow-sm">
                                    <i class="fas fa-crown text-[9px]"></i> <?php echo htmlspecialchars($u['active_pass']); ?>
                                </span>
                            <?php else: ?>
                                <span class="text-slate-600 text-[10px] font-bold uppercase tracking-widest bg-slate-800/50 px-3 py-1.5 rounded-xl border border-white/5 w-fit">Standard Member</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-6 text-center">
                            <div class="flex flex-col items-center">
                                <span class="text-white font-bold text-sm"><?php echo $u['total_orders']; ?></span>
                                <span class="text-[9px] text-slate-600 uppercase font-black tracking-widest mt-0.5">Orders</span>
                            </div>
                        </td>
                        <td class="p-6 text-right">
                            <span class="text-emerald-400 font-extrabold tracking-tight">
                                <?php echo format_admin_currency($u['total_spent'] ?: 0); ?>
                            </span>
                        </td>
                        <td class="p-6 text-right">
                            <div class="flex flex-col items-end">
                                <span class="text-slate-300 text-xs font-medium"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></span>
                                <span class="text-[9px] text-slate-600 uppercase font-bold tracking-widest mt-0.5">Onboarded</span>
                            </div>
                        </td>
                        <td class="p-6 pr-10 text-right">
                            <div class="flex justify-end gap-3">
                                <a href="<?php echo admin_url('users', ['delete' => $u['id']]); ?>" 
                                   class="w-10 h-10 rounded-xl bg-slate-800/50 border border-white/5 text-rose-500 hover:bg-rose-600 hover:text-white hover:border-rose-500 transition-all flex items-center justify-center shadow-sm" 
                                   onclick="return confirm('Account Deletion WARNING: This will remove all user data and access. Continue?')"
                                   title="Remove Customer">
                                    <i class="fas fa-user-xmark text-xs"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if(empty($users)): ?>
                    <tr>
                        <td colspan="8" class="p-24 text-center">
                            <div class="flex flex-col items-center opacity-30">
                                <div class="w-20 h-20 bg-slate-800 rounded-3xl flex items-center justify-center mb-6">
                                    <i class="fas fa-users-slash text-4xl text-slate-600"></i>
                                </div>
                                <h3 class="text-white font-bold text-xl font-heading tracking-tight uppercase">Zero Clients Detected</h3>
                                <p class="text-sm mt-2 font-medium text-slate-500">Your user database is currently empty.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>