<?php
// admin/reports.php

// 1. Handle Add Expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    $title = trim($_POST['title']);
    $amount = (float) $_POST['amount'];
    $category = $_POST['category'];
    $note = trim($_POST['note']);

    if ($title && $amount > 0) {
        $stmt = $pdo->prepare("INSERT INTO expenses (title, amount, category, note) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $amount, $category, $note]);
        redirect(admin_url('reports', ['success' => 1]));
    }
}

// 2. Handle Delete Expense
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM expenses WHERE id = ?")->execute([$id]);
    redirect(admin_url('reports', ['deleted' => 1]));
}

// 3. Stats Calculation
$revenue = get_total_revenue($pdo);
$expenses = get_total_expenses($pdo);
$profit = $revenue - $expenses;
$profit_color = $profit >= 0 ? 'text-blue-400' : 'text-red-400';

// 4. Fetch Expense History
$expense_list = $pdo->query("SELECT * FROM expenses ORDER BY created_at DESC LIMIT 50")->fetchAll();
?>

<div class="mb-8 flex justify-between items-center">
    <div>
        <h1 class="text-3xl font-bold text-white">Financial Reports</h1>
        <p class="text-slate-400 text-sm mt-1">Track revenue, expenses, and net profit.</p>
    </div>
</div>

<!-- Financial Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <!-- Revenue -->
    <div class="bg-slate-800 p-6 rounded-xl border border-slate-700 shadow-lg relative overflow-hidden group">
        <div class="absolute right-0 top-0 p-4 opacity-5 group-hover:scale-110 transition"><i class="fas fa-wallet text-6xl text-green-500"></i></div>
        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Revenue</p>
        <h3 class="text-2xl font-bold text-green-400 mt-1"><?php echo format_admin_currency($revenue); ?></h3>
    </div>

    <!-- Expenses -->
    <div class="bg-slate-800 p-6 rounded-xl border border-slate-700 shadow-lg relative overflow-hidden group">
        <div class="absolute right-0 top-0 p-4 opacity-5 group-hover:scale-110 transition"><i class="fas fa-receipt text-6xl text-red-500"></i></div>
        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Expenses</p>
        <h3 class="text-2xl font-bold text-red-400 mt-1"><?php echo format_admin_currency($expenses); ?></h3>
    </div>

    <!-- Net Profit -->
    <div class="bg-slate-800 p-6 rounded-xl border border-slate-700 shadow-lg relative overflow-hidden group">
        <div class="absolute right-0 top-0 p-4 opacity-5 group-hover:scale-110 transition"><i class="fas fa-chart-line text-6xl text-blue-500"></i></div>
        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Net Profit</p>
        <h3 class="text-2xl font-bold <?php echo $profit_color; ?> mt-1">
            <?php echo ($profit > 0 ? '+' : '') . format_admin_currency($profit); ?>
        </h3>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Add Expense Form -->
    <div class="lg:col-span-1">
        <div class="bg-slate-800 rounded-xl border border-slate-700 p-6 shadow-lg sticky top-6">
            <h3 class="font-bold text-white mb-4 flex items-center gap-2 border-b border-slate-700 pb-2">
                <i class="fas fa-plus-circle text-blue-500"></i> Record Expense
            </h3>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Title</label>
                    <input type="text" name="title" required placeholder="e.g. Server Cost" class="w-full bg-slate-900 border border-slate-600 rounded-lg p-2.5 text-sm text-white focus:border-blue-500 outline-none placeholder-slate-600">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Amount (Ks)</label>
                        <input type="number" step="0.01" name="amount" required placeholder="0.00" class="w-full bg-slate-900 border border-slate-600 rounded-lg p-2.5 text-sm text-white focus:border-blue-500 outline-none placeholder-slate-600">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Category</label>
                        <select name="category" class="w-full bg-slate-900 border border-slate-600 rounded-lg p-2.5 text-sm text-white focus:border-blue-500 outline-none">
                            <option value="General">General</option>
                            <option value="Stock">Stock Purchase</option>
                            <option value="Marketing">Marketing / Ads</option>
                            <option value="Server">Server / Hosting</option>
                            <option value="Salary">Salary</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Note (Optional)</label>
                    <textarea name="note" rows="2" class="w-full bg-slate-900 border border-slate-600 rounded-lg p-2.5 text-sm text-white focus:border-blue-500 outline-none resize-none placeholder-slate-600"></textarea>
                </div>
                <button type="submit" name="add_expense" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-2.5 rounded-lg transition shadow-lg text-sm flex justify-center items-center gap-2">
                    <i class="fas fa-save"></i> Add Record
                </button>
            </form>
        </div>
    </div>

    <!-- Recent Expenses Table -->
    <div class="lg:col-span-2">
        <div class="bg-slate-800 rounded-xl border border-slate-700 overflow-hidden shadow-lg">
            <div class="p-4 border-b border-slate-700 font-bold text-sm text-slate-300 flex justify-between items-center">
                <span>Recent Transactions</span>
                <span class="text-xs bg-slate-700 px-2 py-1 rounded text-slate-400">Last 50</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-900/50 text-slate-400 uppercase text-xs">
                        <tr>
                            <th class="p-4 pl-6">Title</th>
                            <th class="p-4">Category</th>
                            <th class="p-4 text-right">Amount</th>
                            <th class="p-4 text-right">Date</th>
                            <th class="p-4 text-center pr-6">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700">
                        <?php foreach($expense_list as $ex): ?>
                        <tr class="hover:bg-slate-700/30 transition group">
                            <td class="p-4 pl-6 font-medium text-white"><?php echo htmlspecialchars($ex['title']); ?></td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded text-[10px] uppercase font-bold bg-slate-700 text-slate-300 border border-slate-600"><?php echo htmlspecialchars($ex['category']); ?></span>
                            </td>
                            <td class="p-4 text-right font-mono text-red-400">-<?php echo number_format($ex['amount']); ?></td>
                            <td class="p-4 text-right text-slate-500 text-xs"><?php echo date('M d, Y', strtotime($ex['created_at'])); ?></td>
                            <td class="p-4 text-center pr-6">
                                <a href="<?php echo admin_url('reports', ['delete' => $ex['id']]); ?>" 
                                   class="text-slate-600 hover:text-red-400 transition p-2 rounded hover:bg-red-900/20" 
                                   onclick="return confirm('Permanently remove this record?')"
                                   title="Delete Record">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($expense_list)): ?>
                            <tr><td colspan="5" class="p-8 text-center text-slate-500 italic">No expenses recorded yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>