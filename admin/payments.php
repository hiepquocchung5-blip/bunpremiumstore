<?php
// admin/payments.php

// 1. Handle Add Payment Method
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment'])) {
    $bank = trim($_POST['bank_name']);
    $name = trim($_POST['account_name']);
    $number = trim($_POST['account_number']);
    $icon = $_POST['logo_class'];

    if ($bank && $number) {
        $stmt = $pdo->prepare("INSERT INTO payment_methods (bank_name, account_name, account_number, logo_class) VALUES (?, ?, ?, ?)");
        $stmt->execute([$bank, $name, $number, $icon]);
        redirect(admin_url('payments', ['success' => 1]));
    }
}

// 2. Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM payment_methods WHERE id = ?")->execute([$id]);
    redirect(admin_url('payments', ['deleted' => 1]));
}

// 3. Handle Toggle Status
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $pdo->query("UPDATE payment_methods SET is_active = NOT is_active WHERE id = $id");
    redirect(admin_url('payments'));
}

// Fetch Methods
$payments = $pdo->query("SELECT * FROM payment_methods ORDER BY id DESC")->fetchAll();
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-3xl font-bold text-white">Payment Methods</h1>
        <p class="text-slate-400 text-sm mt-1">Manage receiving accounts (KBZPay, Wave).</p>
    </div>
</div>

<?php if(isset($_GET['success'])): ?>
    <div class="bg-green-500/20 text-green-400 p-4 rounded-xl border border-green-500/50 mb-6 flex items-center gap-3">
        <i class="fas fa-check-circle"></i> Payment method added.
    </div>
<?php endif; ?>

<?php if(isset($_GET['deleted'])): ?>
    <div class="bg-red-500/20 text-red-400 p-4 rounded-xl border border-red-500/50 mb-6 flex items-center gap-3">
        <i class="fas fa-trash-alt"></i> Payment method deleted.
    </div>
<?php endif; ?>

<?php if(isset($_GET['updated'])): ?>
    <div class="bg-blue-500/20 text-blue-400 p-4 rounded-xl border border-blue-500/50 mb-6 flex items-center gap-3">
        <i class="fas fa-save"></i> Payment method updated.
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Add Form -->
    <div class="lg:col-span-1">
        <div class="bg-slate-800 p-6 rounded-xl border border-slate-700 shadow-lg sticky top-6">
            <h3 class="font-bold text-white mb-4 border-b border-slate-700 pb-2">Add Account</h3>
            
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Bank / Service Name</label>
                    <input type="text" name="bank_name" placeholder="e.g. KBZPay" required class="w-full bg-slate-900 border border-slate-600 rounded p-2.5 text-white text-sm focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Account Name</label>
                    <input type="text" name="account_name" placeholder="e.g. U Mya" required class="w-full bg-slate-900 border border-slate-600 rounded p-2.5 text-white text-sm focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Account Number</label>
                    <input type="text" name="account_number" placeholder="09xxxxxxxxx" required class="w-full bg-slate-900 border border-slate-600 rounded p-2.5 text-white text-sm font-mono focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Icon Class (FontAwesome)</label>
                    <select name="logo_class" class="w-full bg-slate-900 border border-slate-600 rounded p-2.5 text-white text-sm focus:border-blue-500 outline-none">
                        <option value="fas fa-wallet">Wallet (Generic)</option>
                        <option value="fas fa-money-bill-wave">Wave Money</option>
                        <option value="fas fa-university">Bank Transfer</option>
                        <option value="fas fa-credit-card">Credit Card</option>
                        <option value="fab fa-bitcoin">Crypto</option>
                    </select>
                </div>
                <button type="submit" name="add_payment" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-2.5 rounded-lg transition shadow-lg text-sm flex justify-center items-center gap-2">
                    <i class="fas fa-plus"></i> Add Method
                </button>
            </form>
        </div>
    </div>

    <!-- List -->
    <div class="lg:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4">
        <?php foreach($payments as $pm): ?>
            <div class="bg-slate-800 p-5 rounded-xl border <?php echo $pm['is_active'] ? 'border-slate-700' : 'border-red-900/50 opacity-75'; ?> relative group hover:border-blue-500/50 transition shadow-md">
                
                <div class="absolute top-4 right-4 flex gap-2 opacity-0 group-hover:opacity-100 transition duration-200">
                    <!-- Toggle Status -->
                    <a href="<?php echo admin_url('payments', ['toggle' => $pm['id']]); ?>" 
                       class="text-xs bg-slate-700 hover:bg-slate-600 text-white px-2 py-1 rounded transition" 
                       title="<?php echo $pm['is_active'] ? 'Disable' : 'Enable'; ?>">
                        <i class="fas <?php echo $pm['is_active'] ? 'fa-toggle-on text-green-400' : 'fa-toggle-off text-gray-400'; ?>"></i>
                    </a>
                    
                    <!-- Edit Button (Updated) -->
                    <a href="<?php echo admin_url('payment_edit', ['id' => $pm['id']]); ?>" 
                       class="text-xs bg-slate-700 hover:bg-blue-600 text-white px-2 py-1 rounded transition" 
                       title="Edit">
                        <i class="fas fa-edit"></i>
                    </a>
                    
                    <!-- Delete Button -->
                    <a href="<?php echo admin_url('payments', ['delete' => $pm['id']]); ?>" 
                       class="text-xs bg-slate-700 hover:bg-red-600 text-white px-2 py-1 rounded transition" 
                       onclick="return confirm('Delete this payment method?')" 
                       title="Delete">
                        <i class="fas fa-trash"></i>
                    </a>
                </div>

                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-slate-900 flex items-center justify-center text-blue-400 text-xl border border-slate-700 shrink-0">
                        <i class="<?php echo $pm['logo_class']; ?>"></i>
                    </div>
                    <div class="min-w-0">
                        <h4 class="font-bold text-white truncate"><?php echo htmlspecialchars($pm['bank_name']); ?></h4>
                        <p class="text-xs text-slate-400 truncate"><?php echo htmlspecialchars($pm['account_name']); ?></p>
                        <p class="text-sm font-mono text-green-400 mt-1 select-all"><?php echo htmlspecialchars($pm['account_number']); ?></p>
                    </div>
                </div>
                
                <?php if(!$pm['is_active']): ?>
                    <div class="absolute inset-0 bg-black/40 flex items-center justify-center pointer-events-none rounded-xl">
                        <span class="bg-red-600 text-white text-[10px] font-bold px-2 py-1 rounded uppercase tracking-wider shadow-lg">Disabled</span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        
        <?php if(empty($payments)): ?>
            <div class="col-span-1 md:col-span-2 text-center py-10 border-2 border-dashed border-slate-700 rounded-xl text-slate-500">
                <i class="fas fa-wallet text-4xl mb-3 opacity-30"></i>
                <p class="text-sm">No payment methods added yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>