<?php
// admin/payment_edit.php

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 1. Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    $bank = trim($_POST['bank_name']);
    $name = trim($_POST['account_name']);
    $number = trim($_POST['account_number']);
    $icon = $_POST['logo_class'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($bank && $number) {
        $stmt = $pdo->prepare("UPDATE payment_methods SET bank_name=?, account_name=?, account_number=?, logo_class=?, is_active=? WHERE id=?");
        $stmt->execute([$bank, $name, $number, $icon, $is_active, $id]);
        redirect(admin_url('payments', ['updated' => 1]));
    } else {
        $error = "Bank name and account number are required.";
    }
}

// 2. Fetch Payment
$stmt = $pdo->prepare("SELECT * FROM payment_methods WHERE id = ?");
$stmt->execute([$id]);
$payment = $stmt->fetch();

if (!$payment) {
    echo "<div class='p-6 bg-red-500/20 text-red-400 rounded-xl border border-red-500/50'>Payment method not found. <a href='".admin_url('payments')."' class='underline hover:text-white'>Back</a></div>";
    return;
}
?>

<div class="max-w-xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-white">Edit Payment Method</h1>
        <a href="<?php echo admin_url('payments'); ?>" class="text-slate-400 hover:text-white text-sm flex items-center gap-1"><i class="fas fa-arrow-left"></i> Back</a>
    </div>

    <?php if(isset($error)) echo "<div class='bg-red-500/20 text-red-400 p-4 rounded-xl border border-red-500/50 mb-6 flex items-center gap-2'><i class='fas fa-exclamation-triangle'></i> $error</div>"; ?>

    <div class="bg-slate-800 p-8 rounded-xl border border-slate-700 shadow-xl">
        <form method="POST" class="space-y-6">
            
            <div>
                <label class="block text-xs font-bold text-slate-400 mb-1 uppercase tracking-wider">Bank / Service Name</label>
                <input type="text" name="bank_name" value="<?php echo htmlspecialchars($payment['bank_name']); ?>" required class="w-full bg-slate-900 border border-slate-600 rounded-lg p-3 text-white focus:border-blue-500 outline-none transition">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-400 mb-1 uppercase tracking-wider">Account Name</label>
                <input type="text" name="account_name" value="<?php echo htmlspecialchars($payment['account_name']); ?>" required class="w-full bg-slate-900 border border-slate-600 rounded-lg p-3 text-white focus:border-blue-500 outline-none transition">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-400 mb-1 uppercase tracking-wider">Account Number</label>
                <input type="text" name="account_number" value="<?php echo htmlspecialchars($payment['account_number']); ?>" required class="w-full bg-slate-900 border border-slate-600 rounded-lg p-3 text-white font-mono tracking-wide focus:border-blue-500 outline-none transition">
            </div>

            <div class="grid grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1 uppercase tracking-wider">Icon Style</label>
                    <div class="relative">
                        <select name="logo_class" class="w-full bg-slate-900 border border-slate-600 rounded-lg p-3 text-white text-sm focus:border-blue-500 outline-none appearance-none">
                            <option value="fas fa-wallet" <?php echo $payment['logo_class']=='fas fa-wallet'?'selected':''; ?>>Wallet</option>
                            <option value="fas fa-money-bill-wave" <?php echo $payment['logo_class']=='fas fa-money-bill-wave'?'selected':''; ?>>Wave Money</option>
                            <option value="fas fa-university" <?php echo $payment['logo_class']=='fas fa-university'?'selected':''; ?>>Bank</option>
                            <option value="fas fa-credit-card" <?php echo $payment['logo_class']=='fas fa-credit-card'?'selected':''; ?>>Card</option>
                            <option value="fab fa-bitcoin" <?php echo $payment['logo_class']=='fab fa-bitcoin'?'selected':''; ?>>Crypto</option>
                        </select>
                        <i class="fas fa-chevron-down absolute right-3 top-3.5 text-slate-500 pointer-events-none"></i>
                    </div>
                </div>
                
                <div class="flex items-end pb-3">
                    <label class="flex items-center gap-3 cursor-pointer group">
                        <div class="relative">
                            <input type="checkbox" name="is_active" value="1" <?php echo $payment['is_active'] ? 'checked' : ''; ?> class="peer sr-only">
                            <div class="w-11 h-6 bg-slate-700 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                        </div>
                        <span class="text-sm font-bold text-slate-400 group-hover:text-white transition">Active Status</span>
                    </label>
                </div>
            </div>

            <div class="pt-6 border-t border-slate-700 flex gap-4">
                <a href="<?php echo admin_url('payments'); ?>" class="flex-1 bg-slate-700 hover:bg-slate-600 text-white font-bold py-3 rounded-lg text-center transition">Cancel</a>
                <button type="submit" name="update_payment" class="flex-1 bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 rounded-lg shadow-lg transition flex justify-center items-center gap-2">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>