<?php
// admin/coupons.php

// 1. Handle Add Coupon
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_coupon'])) {
    $code = strtoupper(trim($_POST['code']));
    $percent = (int)$_POST['percent'];
    $limit = (int)$_POST['limit'];
    $expiry = $_POST['expiry']; // YYYY-MM-DD

    if ($code && $percent > 0 && $percent <= 100) {
        // Prevent duplicates
        $check = $pdo->prepare("SELECT id FROM coupons WHERE code = ?");
        $check->execute([$code]);
        if ($check->rowCount() == 0) {
            $stmt = $pdo->prepare("INSERT INTO coupons (code, discount_percent, max_usage, expires_at) VALUES (?, ?, ?, ?)");
            $stmt->execute([$code, $percent, $limit, $expiry . ' 23:59:59']);
            redirect(admin_url('coupons', ['success' => 1]));
        } else {
            $error = "Coupon code already exists.";
        }
    }
}

// 2. Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM coupons WHERE id = ?")->execute([$id]);
    redirect(admin_url('coupons', ['deleted' => 1]));
}

// Fetch Coupons
$coupons = $pdo->query("SELECT * FROM coupons ORDER BY created_at DESC")->fetchAll();
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-3xl font-bold text-white">Coupon Manager</h1>
        <p class="text-slate-400 text-sm mt-1">Create discount codes for marketing campaigns.</p>
    </div>
</div>

<?php if(isset($_GET['success'])): ?>
    <div class="bg-green-500/20 text-green-400 p-4 rounded-xl border border-green-500/50 mb-6 flex items-center gap-3">
        <i class="fas fa-check-circle"></i> Coupon created successfully.
    </div>
<?php endif; ?>

<?php if(isset($error)): ?>
    <div class="bg-red-500/20 text-red-400 p-4 rounded-xl border border-red-500/50 mb-6">
        <?php echo $error; ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Add Form -->
    <div class="lg:col-span-1">
        <div class="bg-slate-800 p-6 rounded-xl border border-slate-700 shadow-lg sticky top-6">
            <h3 class="font-bold text-white mb-4 border-b border-slate-700 pb-2 flex items-center gap-2">
                <i class="fas fa-ticket-alt text-yellow-500"></i> Create Coupon
            </h3>
            
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Code</label>
                    <input type="text" name="code" placeholder="e.g. SALE10" required class="w-full bg-slate-900 border border-slate-600 rounded-lg p-2.5 text-white uppercase font-bold focus:border-yellow-500 outline-none placeholder-slate-600">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Discount (%)</label>
                        <input type="number" name="percent" placeholder="10" min="1" max="100" required class="w-full bg-slate-900 border border-slate-600 rounded-lg p-2.5 text-white focus:border-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Usage Limit</label>
                        <input type="number" name="limit" placeholder="100" required class="w-full bg-slate-900 border border-slate-600 rounded-lg p-2.5 text-white focus:border-blue-500 outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Expiry Date</label>
                    <input type="date" name="expiry" required class="w-full bg-slate-900 border border-slate-600 rounded-lg p-2.5 text-white focus:border-blue-500 outline-none">
                </div>

                <button type="submit" name="add_coupon" class="w-full bg-yellow-600 hover:bg-yellow-500 text-black font-bold py-2.5 rounded-lg transition shadow-lg text-sm">
                    Create Code
                </button>
            </form>
        </div>
    </div>

    <!-- Coupon List -->
    <div class="lg:col-span-2">
        <div class="bg-slate-800 rounded-xl border border-slate-700 overflow-hidden shadow-lg">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-900/50 text-slate-400 uppercase text-xs">
                        <tr>
                            <th class="p-4">Code</th>
                            <th class="p-4">Discount</th>
                            <th class="p-4 text-center">Usage</th>
                            <th class="p-4">Expires</th>
                            <th class="p-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700">
                        <?php foreach($coupons as $c): ?>
                            <?php 
                                $is_expired = strtotime($c['expires_at']) < time();
                                $is_full = $c['used_count'] >= $c['max_usage'];
                                $status_class = ($is_expired || $is_full) ? 'opacity-50 grayscale' : '';
                            ?>
                            <tr class="hover:bg-slate-700/30 transition <?php echo $status_class; ?>">
                                <td class="p-4">
                                    <span class="font-mono font-bold text-yellow-400 bg-yellow-900/20 px-2 py-1 rounded border border-yellow-500/30"><?php echo htmlspecialchars($c['code']); ?></span>
                                    <?php if($is_expired): ?><span class="text-red-500 text-[10px] ml-2 font-bold bg-red-900/20 px-1 rounded">EXPIRED</span><?php endif; ?>
                                </td>
                                <td class="p-4 font-bold text-white"><?php echo $c['discount_percent']; ?>%</td>
                                <td class="p-4 text-center">
                                    <span class="text-white font-bold"><?php echo $c['used_count']; ?></span> 
                                    <span class="text-slate-500">/ <?php echo $c['max_usage']; ?></span>
                                </td>
                                <td class="p-4 text-slate-400 text-xs"><?php echo date('M d, Y', strtotime($c['expires_at'])); ?></td>
                                <td class="p-4 text-right">
                                    <a href="<?php echo admin_url('coupons', ['delete' => $c['id']]); ?>" class="text-red-400 hover:text-white transition p-2 rounded hover:bg-red-900/20" onclick="return confirm('Delete coupon?')"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if(empty($coupons)): ?>
                            <tr><td colspan="5" class="p-8 text-center text-slate-500">No active coupons.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>