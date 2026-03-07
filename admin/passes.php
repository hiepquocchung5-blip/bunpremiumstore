<?php
// admin/passes.php
// PRODUCTION v1.0 - Agent Pass Management

// 1. Handle Add Pass
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_pass'])) {
    $name = trim($_POST['name']);
    $price = (float)$_POST['price'];
    $discount = (int)$_POST['discount_percent'];
    $duration = (int)$_POST['duration_days'];
    $desc = trim($_POST['description']);

    if ($name && $price >= 0 && $discount > 0) {
        $stmt = $pdo->prepare("INSERT INTO passes (name, price, discount_percent, duration_days, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $price, $discount, $duration, $desc]);
        redirect(admin_url('passes', ['success' => 1]));
    } else {
        $error = "Invalid inputs. Ensure discount is greater than 0.";
    }
}

// 2. Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $pdo->prepare("DELETE FROM passes WHERE id = ?")->execute([$id]);
        redirect(admin_url('passes', ['deleted' => 1]));
    } catch (Exception $e) {
        $error = "Cannot delete pass. Users are currently assigned to it.";
    }
}

// 3. Handle Toggle Status
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $pdo->query("UPDATE passes SET is_active = NOT is_active WHERE id = $id");
    redirect(admin_url('passes'));
}

// Fetch Passes
$passes = $pdo->query("SELECT * FROM passes ORDER BY price ASC")->fetchAll();
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-3xl font-bold text-white flex items-center gap-3">
            Agent Tiers <i class="fas fa-crown text-yellow-500"></i>
        </h1>
        <p class="text-slate-400 text-sm mt-1">Configure reseller passes, discounts, and pricing.</p>
    </div>
</div>

<?php if(isset($_GET['success'])): ?>
    <div class="bg-green-500/20 text-green-400 p-4 rounded-xl border border-green-500/50 mb-6 flex items-center gap-3">
        <i class="fas fa-check-circle"></i> Tier added successfully.
    </div>
<?php endif; ?>

<?php if(isset($_GET['deleted'])): ?>
    <div class="bg-red-500/20 text-red-400 p-4 rounded-xl border border-red-500/50 mb-6 flex items-center gap-3">
        <i class="fas fa-trash-alt"></i> Tier removed from system.
    </div>
<?php endif; ?>

<?php if(isset($error)): ?>
    <div class="bg-red-500/20 text-red-400 p-4 rounded-xl border border-red-500/50 mb-6 flex items-center gap-2">
        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Add Form -->
    <div class="lg:col-span-1">
        <div class="bg-slate-800 p-6 rounded-xl border border-slate-700 shadow-lg sticky top-6">
            <h3 class="font-bold text-white mb-4 border-b border-slate-700 pb-2 flex items-center gap-2">
                <i class="fas fa-plus text-yellow-500"></i> Create Agent Pass
            </h3>
            
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1 uppercase tracking-wider">Tier Name</label>
                    <input type="text" name="name" placeholder="e.g. Diamond Partner" required class="w-full bg-slate-900 border border-slate-600 rounded-lg p-2.5 text-white text-sm focus:border-yellow-500 outline-none transition">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1 uppercase tracking-wider">Price (Ks)</label>
                        <input type="number" name="price" placeholder="50000" required class="w-full bg-slate-900 border border-slate-600 rounded-lg p-2.5 text-white text-sm focus:border-yellow-500 outline-none transition font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1 uppercase tracking-wider">Discount %</label>
                        <input type="number" name="discount_percent" placeholder="35" required min="1" max="100" class="w-full bg-slate-900 border border-slate-600 rounded-lg p-2.5 text-yellow-400 text-sm focus:border-yellow-500 outline-none transition font-mono font-bold">
                    </div>
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1 uppercase tracking-wider">Duration (Days)</label>
                    <input type="number" name="duration_days" placeholder="30" required class="w-full bg-slate-900 border border-slate-600 rounded-lg p-2.5 text-white text-sm focus:border-yellow-500 outline-none transition font-mono">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1 uppercase tracking-wider">Description</label>
                    <textarea name="description" rows="3" placeholder="Privileges and benefits..." class="w-full bg-slate-900 border border-slate-600 rounded-lg p-2.5 text-white text-sm focus:border-yellow-500 outline-none resize-none transition"></textarea>
                </div>

                <button type="submit" name="add_pass" class="w-full bg-yellow-600 hover:bg-yellow-500 text-slate-900 font-black py-3 rounded-lg transition shadow-[0_0_15px_rgba(234,179,8,0.3)] text-sm flex justify-center items-center gap-2">
                    <i class="fas fa-crown"></i> Deploy Pass
                </button>
            </form>
        </div>
    </div>

    <!-- Pass List -->
    <div class="lg:col-span-2">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <?php foreach($passes as $p): ?>
                <div class="bg-slate-900/80 backdrop-blur-xl p-6 rounded-2xl border <?php echo $p['is_active'] ? 'border-yellow-500/40 shadow-[0_10px_30px_rgba(234,179,8,0.1)]' : 'border-slate-700 opacity-60 grayscale'; ?> flex flex-col relative group transition-all">
                    
                    <div class="absolute top-4 right-4 flex gap-2 opacity-0 group-hover:opacity-100 transition duration-200 z-20">
                        <a href="<?php echo admin_url('passes', ['toggle' => $p['id']]); ?>" class="text-xs bg-slate-800 hover:bg-slate-700 text-white w-8 h-8 flex items-center justify-center rounded transition border border-slate-600" title="<?php echo $p['is_active'] ? 'Disable' : 'Enable'; ?>">
                            <i class="fas <?php echo $p['is_active'] ? 'fa-toggle-on text-green-400' : 'fa-toggle-off text-gray-400'; ?>"></i>
                        </a>
                        <a href="<?php echo admin_url('passes', ['delete' => $p['id']]); ?>" class="text-xs bg-slate-800 hover:bg-red-900/40 hover:text-red-400 text-white w-8 h-8 flex items-center justify-center rounded transition border border-slate-600" onclick="return confirm('Delete this agent tier?')" title="Delete">
                            <i class="fas fa-trash"></i>
                        </a>
                    </div>

                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-12 h-12 bg-yellow-500/10 rounded-full flex items-center justify-center text-yellow-500 border border-yellow-500/30 text-xl font-black shrink-0">
                            <?php echo $p['discount_percent']; ?>%
                        </div>
                        <div>
                            <h4 class="font-bold text-white text-lg tracking-tight"><?php echo htmlspecialchars($p['name']); ?></h4>
                            <p class="text-xs text-slate-400 font-mono"><?php echo $p['duration_days']; ?> Days Validity</p>
                        </div>
                    </div>
                    
                    <div class="text-3xl font-black text-transparent bg-clip-text bg-gradient-to-r from-yellow-200 to-yellow-600 drop-shadow-sm mb-4">
                        <?php echo number_format($p['price']); ?> <span class="text-sm text-yellow-600 font-bold">Ks</span>
                    </div>

                    <p class="text-slate-400 text-xs leading-relaxed flex-1 border-t border-slate-700/50 pt-4 mt-auto">
                        <?php echo nl2br(htmlspecialchars($p['description'])); ?>
                    </p>

                    <?php if(!$p['is_active']): ?>
                        <div class="absolute inset-0 flex items-center justify-center pointer-events-none rounded-2xl bg-black/20">
                            <span class="bg-red-600 text-white text-[10px] font-bold px-3 py-1 rounded uppercase tracking-wider shadow-lg">Disabled</span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <?php if(empty($passes)): ?>
                <div class="col-span-1 md:col-span-2 text-center py-16 bg-slate-800/50 rounded-2xl border-2 border-dashed border-slate-700 text-slate-500">
                    <i class="fas fa-id-card-alt text-5xl mb-4 opacity-50"></i>
                    <p class="font-bold tracking-wide">No Agent Tiers Deployed.</p>
                    <p class="text-xs mt-1">Create a pass to allow users to become resellers.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>