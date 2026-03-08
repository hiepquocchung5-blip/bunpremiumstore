<?php
// admin/coupons.php
// PRODUCTION v1.0 - Full CRUD for Promotional Codes

// 1. Handle Create (Add Coupon)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_coupon'])) {
    $code = strtoupper(trim($_POST['code']));
    $percent = (int)$_POST['discount_percent'];
    $limit = (int)$_POST['max_usage'];
    $expiry = $_POST['expires_at'];

    // Validation
    if (empty($code)) {
        $error = "Transmission code cannot be empty.";
    } elseif ($percent <= 0 || $percent > 100) {
        $error = "Discount must be between 1 and 100 percent.";
    } elseif ($limit <= 0) {
        $error = "Maximum usage must be at least 1.";
    } elseif (empty($expiry) || strtotime($expiry) <= time()) {
        $error = "Expiry date must be set in the future.";
    } else {
        // Prevent duplicates
        $check = $pdo->prepare("SELECT id FROM coupons WHERE code = ?");
        $check->execute([$code]);
        
        if ($check->rowCount() > 0) {
            $error = "Transmission code '{$code}' already exists in the matrix.";
        } else {
            try {
                // Ensure time is set to end of day if not specified
                if (strpos($expiry, ' ') === false) {
                    $expiry .= ' 23:59:59';
                }
                
                $stmt = $pdo->prepare("INSERT INTO coupons (code, discount_percent, max_usage, expires_at) VALUES (?, ?, ?, ?)");
                $stmt->execute([$code, $percent, $limit, $expiry]);
                redirect(admin_url('coupons', ['success' => 'created']));
            } catch (PDOException $e) {
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }
}

// 2. Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM coupons WHERE id = ?")->execute([$id]);
    redirect(admin_url('coupons', ['success' => 'deleted']));
}

// 3. Handle Toggle Status
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $pdo->query("UPDATE coupons SET is_active = NOT is_active WHERE id = $id");
    redirect(admin_url('coupons'));
}

// 4. Fetch All Coupons
$sql = "SELECT *, 
        (expires_at < NOW()) as is_expired,
        (used_count >= max_usage) as is_depleted
        FROM coupons 
        ORDER BY is_active DESC, created_at DESC";
$coupons = $pdo->query($sql)->fetchAll();

// Telemetry Calculation
$active_count = 0;
$total_discount_given = 0; // Conceptual metric
foreach($coupons as $c) {
    if($c['is_active'] && !$c['is_expired'] && !$c['is_depleted']) $active_count++;
}
?>

<div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div>
        <h1 class="text-3xl font-bold text-white tracking-tight flex items-center gap-3">
            Promo Matrix <i class="fas fa-ticket-alt text-[#00f0ff]"></i>
        </h1>
        <p class="text-slate-400 text-sm mt-1">Generate and monitor promotional transmission codes.</p>
    </div>
</div>

<!-- Status Messages -->
<?php if(isset($_GET['success'])): ?>
    <div class="bg-green-500/10 border border-green-500/30 text-green-400 p-4 rounded-xl mb-6 flex items-center gap-3 shadow-[0_0_15px_rgba(34,197,94,0.1)]">
        <i class="fas fa-check-circle text-lg"></i>
        <span>
            <?php 
                if($_GET['success'] == 'created') echo "New promotional code injected into the matrix.";
                if($_GET['success'] == 'deleted') echo "Code permanently purged from the system.";
            ?>
        </span>
    </div>
<?php endif; ?>

<?php if(isset($error)): ?>
    <div class="bg-red-500/10 border border-red-500/30 text-red-400 p-4 rounded-xl mb-6 flex items-center gap-3 animate-pulse shadow-[0_0_15px_rgba(239,68,68,0.1)]">
        <i class="fas fa-exclamation-triangle text-lg"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- LEFT: Add Form -->
    <div class="lg:col-span-1">
        <div class="bg-slate-900/80 p-6 rounded-2xl border border-[#00f0ff]/20 shadow-[0_0_20px_rgba(0,240,255,0.05)] relative overflow-hidden h-fit sticky top-6">
            <div class="absolute -right-10 -top-10 w-32 h-32 bg-[#00f0ff]/10 rounded-full blur-3xl pointer-events-none"></div>
            
            <h3 class="font-bold text-white mb-5 border-b border-slate-700/50 pb-3 flex items-center gap-2 relative z-10">
                <i class="fas fa-plus-circle text-[#00f0ff]"></i> Generate Code
            </h3>
            
            <form method="POST" class="space-y-4 relative z-10">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Transmission Code</label>
                    <div class="relative">
                        <i class="fas fa-terminal absolute left-3 top-3.5 text-slate-500 text-xs"></i>
                        <input type="text" name="code" placeholder="e.g. CYBER20" required 
                               class="w-full bg-slate-800/50 border border-slate-600 rounded-xl py-2.5 pl-9 pr-3 text-white font-mono uppercase focus:border-[#00f0ff] outline-none shadow-inner transition-colors">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Discount %</label>
                        <div class="relative">
                            <input type="number" name="discount_percent" placeholder="15" required min="1" max="100"
                                   class="w-full bg-slate-800/50 border border-slate-600 rounded-xl py-2.5 pl-3 pr-8 text-yellow-400 font-bold focus:border-yellow-500 outline-none shadow-inner transition-colors">
                            <i class="fas fa-percent absolute right-3 top-3 text-slate-500 text-xs"></i>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Max Uses</label>
                        <input type="number" name="max_usage" placeholder="100" required min="1"
                               class="w-full bg-slate-800/50 border border-slate-600 rounded-xl p-2.5 text-white focus:border-[#00f0ff] outline-none shadow-inner transition-colors">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Expiry Date</label>
                    <input type="datetime-local" name="expires_at" required 
                           class="w-full bg-slate-800/50 border border-slate-600 rounded-xl p-2.5 text-white text-sm focus:border-[#00f0ff] outline-none shadow-inner transition-colors cursor-pointer">
                </div>

                <div class="pt-3">
                    <button type="submit" name="add_coupon" class="w-full bg-gradient-to-r from-blue-600 to-[#00f0ff] hover:from-blue-500 hover:to-[#00f0ff] text-slate-900 font-black py-3 rounded-xl shadow-[0_0_15px_rgba(0,240,255,0.3)] transition transform active:scale-95 flex justify-center items-center gap-2 uppercase tracking-widest text-xs">
                        <i class="fas fa-bolt"></i> Initialize
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- RIGHT: Code List -->
    <div class="lg:col-span-2 flex flex-col h-full">
        
        <!-- Summary Header -->
        <div class="bg-slate-800/80 backdrop-blur p-4 rounded-2xl border border-slate-700 mb-6 shadow-lg shrink-0 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 bg-green-500/10 rounded-xl flex items-center justify-center text-green-400 border border-green-500/20">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Active Codes</p>
                    <p class="text-xl font-black text-white leading-none"><?php echo $active_count; ?></p>
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <div class="bg-slate-900/60 backdrop-blur rounded-2xl border border-slate-700 overflow-hidden shadow-2xl flex-grow flex flex-col">
            <div class="overflow-x-auto flex-grow custom-scrollbar">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-800/80 text-slate-400 uppercase text-[10px] font-bold tracking-widest sticky top-0 z-20 backdrop-blur-md">
                        <tr>
                            <th class="p-4 pl-6">Code</th>
                            <th class="p-4 text-center">Value</th>
                            <th class="p-4 text-center">Usage</th>
                            <th class="p-4 text-right">Expiration</th>
                            <th class="p-4 text-right pr-6">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        <?php foreach($coupons as $c): 
                            $status_class = '';
                            $badge = '';
                            
                            if (!$c['is_active']) {
                                $status_class = 'opacity-50 grayscale';
                                $badge = '<span class="text-[8px] bg-slate-700 text-slate-300 px-1.5 py-0.5 rounded uppercase font-bold ml-2">Disabled</span>';
                            } elseif ($c['is_expired']) {
                                $status_class = 'opacity-60 bg-red-900/5';
                                $badge = '<span class="text-[8px] bg-red-500/20 text-red-400 border border-red-500/30 px-1.5 py-0.5 rounded uppercase font-bold ml-2">Expired</span>';
                            } elseif ($c['is_depleted']) {
                                $status_class = 'opacity-60 bg-orange-900/5';
                                $badge = '<span class="text-[8px] bg-orange-500/20 text-orange-400 border border-orange-500/30 px-1.5 py-0.5 rounded uppercase font-bold ml-2">Depleted</span>';
                            }
                            
                            $usage_pct = ($c['used_count'] / $c['max_usage']) * 100;
                            $bar_color = $usage_pct >= 90 ? 'bg-red-500' : ($usage_pct >= 50 ? 'bg-yellow-500' : 'bg-green-500');
                        ?>
                            <tr class="hover:bg-slate-800/40 transition-colors group <?php echo $status_class; ?>">
                                
                                <!-- Code -->
                                <td class="p-4 pl-6 align-middle">
                                    <div class="flex items-center">
                                        <span class="font-mono font-bold text-[#00f0ff] bg-[#00f0ff]/10 px-2 py-1 rounded border border-[#00f0ff]/20 tracking-wider">
                                            <?php echo htmlspecialchars($c['code']); ?>
                                        </span>
                                        <?php echo $badge; ?>
                                    </div>
                                </td>
                                
                                <!-- Value -->
                                <td class="p-4 text-center align-middle">
                                    <span class="text-yellow-400 font-black text-lg drop-shadow-[0_0_5px_rgba(234,179,8,0.5)]">
                                        -<?php echo $c['discount_percent']; ?>%
                                    </span>
                                </td>
                                
                                <!-- Usage Progress -->
                                <td class="p-4 text-center align-middle w-1/4">
                                    <div class="flex flex-col items-center gap-1">
                                        <span class="text-xs font-bold <?php echo $c['is_depleted'] ? 'text-red-400' : 'text-white'; ?>">
                                            <?php echo $c['used_count']; ?> <span class="text-slate-500 font-normal">/ <?php echo $c['max_usage']; ?></span>
                                        </span>
                                        <div class="w-full bg-slate-800 h-1.5 rounded-full overflow-hidden border border-slate-700">
                                            <div class="h-full <?php echo $bar_color; ?>" style="width: <?php echo min(100, $usage_pct); ?>%;"></div>
                                        </div>
                                    </div>
                                </td>
                                
                                <!-- Expiry -->
                                <td class="p-4 text-right align-middle">
                                    <div class="text-xs <?php echo $c['is_expired'] ? 'text-red-400' : 'text-slate-300'; ?>">
                                        <?php echo date('M d, Y', strtotime($c['expires_at'])); ?>
                                    </div>
                                    <div class="text-[10px] text-slate-500 font-mono">
                                        <?php echo date('H:i', strtotime($c['expires_at'])); ?>
                                    </div>
                                </td>
                                
                                <!-- Actions -->
                                <td class="p-4 text-right pr-6 align-middle">
                                    <div class="flex justify-end gap-2">
                                        <a href="<?php echo admin_url('coupons', ['toggle' => $c['id']]); ?>" 
                                           class="w-8 h-8 rounded-lg bg-slate-800 border border-slate-700 flex items-center justify-center transition shadow-sm <?php echo $c['is_active'] ? 'text-slate-400 hover:text-orange-400' : 'text-green-400 hover:text-green-300 border-green-900/50 bg-green-900/20'; ?>" 
                                           title="<?php echo $c['is_active'] ? 'Disable' : 'Enable'; ?>">
                                            <i class="fas <?php echo $c['is_active'] ? 'fa-pause' : 'fa-play'; ?> text-xs"></i>
                                        </a>
                                        <a href="<?php echo admin_url('coupons', ['delete' => $c['id']]); ?>" 
                                           class="w-8 h-8 rounded-lg bg-slate-800 border border-slate-700 text-red-400 hover:text-white hover:bg-red-600 hover:border-red-500 transition-all flex items-center justify-center shadow-sm"
                                           onclick="return confirm('CRITICAL: Permanently purge this code?')" title="Purge">
                                           <i class="fas fa-trash text-xs"></i>
                                        </a>
                                    </div>
                                </td>
                                
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if(empty($coupons)): ?>
                            <tr>
                                <td colspan="5" class="p-12 text-center text-slate-500">
                                    <i class="fas fa-ticket-alt text-4xl mb-3 opacity-30"></i>
                                    <p class="font-medium tracking-wide">No promotional codes found in the matrix.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="p-3 border-t border-slate-700/80 bg-slate-800/50 text-center text-[10px] text-slate-500 uppercase tracking-widest font-bold shrink-0">
                Total Records: <?php echo count($coupons); ?>
            </div>
        </div>
    </div>
</div>