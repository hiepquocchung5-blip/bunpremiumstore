<?php
// admin/keys.php
// PRODUCTION v3.0 - Full CRUD, Stock Telemetry & Neon UI

// 1. Handle Create (Add Keys)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_keys'])) {
    $product_id = (int)$_POST['product_id'];
    $keys_raw = $_POST['keys_input'];
    
    if ($product_id > 0 && !empty($keys_raw)) {
        $keys_array = explode("\n", $keys_raw);
        $added_count = 0;
        
        $stmt = $pdo->prepare("INSERT INTO product_keys (product_id, key_content) VALUES (?, ?)");
        
        foreach ($keys_array as $k) {
            $clean_key = trim($k);
            if (!empty($clean_key)) {
                $stmt->execute([$product_id, $clean_key]);
                $added_count++;
            }
        }
        
        if ($added_count > 0) {
            redirect(admin_url('keys', ['product_id' => $product_id, 'success_added' => $added_count]));
        }
    } else {
        $error = "Please select a product and input at least one key.";
    }
}

// 2. Handle Update (Edit Key)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_key'])) {
    $key_id = (int)$_POST['key_id'];
    $new_content = trim($_POST['key_content']);
    
    if ($key_id > 0 && !empty($new_content)) {
        // Only allow editing if the key hasn't been sold yet
        $stmt = $pdo->prepare("UPDATE product_keys SET key_content = ? WHERE id = ? AND is_sold = 0");
        if ($stmt->execute([$new_content, $key_id])) {
            redirect(admin_url('keys', ['updated' => 1]));
        } else {
            $error = "Failed to update key. It may have already been sold.";
        }
    }
}

// 3. Handle Delete (Single Key)
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Only delete if unsold
    $stmt = $pdo->prepare("DELETE FROM product_keys WHERE id = ? AND is_sold = 0");
    $stmt->execute([$id]);
    redirect(admin_url('keys', ['deleted' => 1]));
}

// 4. Handle Bulk Delete (Clear Unsold)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_unsold'])) {
    $target_product = (int)$_POST['target_product_id'];
    if ($target_product > 0) {
        $stmt = $pdo->prepare("DELETE FROM product_keys WHERE product_id = ? AND is_sold = 0");
        $stmt->execute([$target_product]);
        $cleared = $stmt->rowCount();
        redirect(admin_url('keys', ['cleared' => $cleared]));
    }
}

// 5. Filters Logic
$product_filter = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

$where = ["p.delivery_type = 'unique'"];
$params = [];

if ($product_filter > 0) {
    $where[] = "k.product_id = ?";
    $params[] = $product_filter;
}

if ($status_filter === 'sold') {
    $where[] = "k.is_sold = 1";
} elseif ($status_filter === 'unsold') {
    $where[] = "k.is_sold = 0";
}

$where_sql = implode(' AND ', $where);

// 6. Fetch Keys (Read)
$sql = "SELECT k.*, p.name as product_name 
        FROM product_keys k 
        JOIN products p ON k.product_id = p.id 
        WHERE $where_sql 
        ORDER BY k.is_sold ASC, k.id DESC 
        LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$keys = $stmt->fetchAll();

// 7. Fetch Products List (For Dropdowns)
$products = $pdo->query("SELECT id, name FROM products WHERE delivery_type = 'unique' ORDER BY name ASC")->fetchAll();

// 8. Calculate Stock Telemetry
$stat_total = 0;
$stat_unsold = 0;
$stat_sold = 0;

if ($product_filter > 0) {
    $stats = $pdo->prepare("SELECT 
        COUNT(*) as total, 
        SUM(CASE WHEN is_sold = 0 THEN 1 ELSE 0 END) as unsold,
        SUM(CASE WHEN is_sold = 1 THEN 1 ELSE 0 END) as sold 
        FROM product_keys WHERE product_id = ?");
    $stats->execute([$product_filter]);
    $res = $stats->fetch();
    $stat_total = $res['total'] ?: 0;
    $stat_unsold = $res['unsold'] ?: 0;
    $stat_sold = $res['sold'] ?: 0;
} else {
    $stats = $pdo->query("SELECT 
        COUNT(*) as total, 
        SUM(CASE WHEN is_sold = 0 THEN 1 ELSE 0 END) as unsold,
        SUM(CASE WHEN is_sold = 1 THEN 1 ELSE 0 END) as sold 
        FROM product_keys")->fetch();
    $stat_total = $stats['total'] ?: 0;
    $stat_unsold = $stats['unsold'] ?: 0;
    $stat_sold = $stats['sold'] ?: 0;
}
?>

<div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div>
        <h1 class="text-3xl font-bold text-white tracking-tight flex items-center gap-3">
            Stock Management <span class="h-2 w-2 rounded-full bg-[#00f0ff] shadow-[0_0_10px_#00f0ff] animate-pulse"></span>
        </h1>
        <p class="text-slate-400 text-sm mt-1">Audit inventory, inject new codes, and monitor distribution.</p>
    </div>
</div>

<!-- Status Messages -->
<?php if(isset($_GET['success_added'])): ?>
    <div class="bg-green-500/10 border border-green-500/30 text-green-400 p-4 rounded-xl mb-6 flex items-center gap-3 shadow-[0_0_15px_rgba(34,197,94,0.1)]">
        <i class="fas fa-database text-lg"></i> Successfully injected <strong><?php echo (int)$_GET['success_added']; ?></strong> new assets into the matrix.
    </div>
<?php endif; ?>

<?php if(isset($_GET['updated'])): ?>
    <div class="bg-blue-500/10 border border-blue-500/30 text-blue-400 p-4 rounded-xl mb-6 flex items-center gap-3 shadow-[0_0_15px_rgba(59,130,246,0.1)]">
        <i class="fas fa-edit text-lg"></i> Asset data updated successfully.
    </div>
<?php endif; ?>

<?php if(isset($_GET['deleted'])): ?>
    <div class="bg-red-500/10 border border-red-500/30 text-red-400 p-4 rounded-xl mb-6 flex items-center gap-3 shadow-[0_0_15px_rgba(239,68,68,0.1)]">
        <i class="fas fa-trash-alt text-lg"></i> Asset permanently purged.
    </div>
<?php endif; ?>

<?php if(isset($_GET['cleared'])): ?>
    <div class="bg-orange-500/10 border border-orange-500/30 text-orange-400 p-4 rounded-xl mb-6 flex items-center gap-3 shadow-[0_0_15px_rgba(249,115,22,0.1)]">
        <i class="fas fa-broom text-lg"></i> Bulk purge complete. Removed <strong><?php echo (int)$_GET['cleared']; ?></strong> unsold assets.
    </div>
<?php endif; ?>

<?php if(isset($error)): ?>
    <div class="bg-red-500/10 border border-red-500/30 text-red-400 p-4 rounded-xl mb-6 flex items-center gap-3 animate-pulse">
        <i class="fas fa-exclamation-triangle text-lg"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<!-- Telemetry Stats -->
<div class="grid grid-cols-3 gap-4 mb-8">
    <div class="bg-slate-800/60 backdrop-blur border border-slate-700/50 p-4 rounded-2xl shadow-inner relative overflow-hidden group">
        <div class="absolute -right-4 -bottom-4 w-16 h-16 bg-blue-500/10 rounded-full blur-xl group-hover:bg-blue-500/20 transition"></div>
        <p class="text-[10px] text-slate-500 uppercase tracking-widest font-bold mb-1">Total Monitored</p>
        <p class="text-2xl font-black text-white font-mono"><?php echo number_format($stat_total); ?></p>
    </div>
    <div class="bg-slate-800/60 backdrop-blur border border-slate-700/50 p-4 rounded-2xl shadow-inner relative overflow-hidden group">
        <div class="absolute -right-4 -bottom-4 w-16 h-16 bg-green-500/10 rounded-full blur-xl group-hover:bg-green-500/20 transition"></div>
        <p class="text-[10px] text-slate-500 uppercase tracking-widest font-bold mb-1">Available (Unsold)</p>
        <p class="text-2xl font-black text-green-400 font-mono"><?php echo number_format($stat_unsold); ?></p>
    </div>
    <div class="bg-slate-800/60 backdrop-blur border border-slate-700/50 p-4 rounded-2xl shadow-inner relative overflow-hidden group">
        <div class="absolute -right-4 -bottom-4 w-16 h-16 bg-red-500/10 rounded-full blur-xl group-hover:bg-red-500/20 transition"></div>
        <p class="text-[10px] text-slate-500 uppercase tracking-widest font-bold mb-1">Distributed (Sold)</p>
        <p class="text-2xl font-black text-red-400 font-mono"><?php echo number_format($stat_sold); ?></p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- LEFT: ADD KEYS FORM -->
    <div class="lg:col-span-1 space-y-6">
        <div class="bg-slate-900/80 p-6 rounded-2xl border border-[#00f0ff]/20 shadow-[0_0_20px_rgba(0,240,255,0.05)] relative overflow-hidden h-fit">
            <div class="absolute top-0 right-0 w-32 h-32 bg-[#00f0ff]/10 rounded-full blur-3xl pointer-events-none"></div>
            
            <h3 class="font-bold text-white mb-5 border-b border-slate-700/50 pb-3 flex items-center gap-2 relative z-10">
                <i class="fas fa-layer-group text-[#00f0ff]"></i> Inject New Stock
            </h3>
            
            <form method="POST" class="space-y-4 relative z-10">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Target Node (Product)</label>
                    <div class="relative">
                        <select name="product_id" required class="w-full bg-slate-800/50 border border-slate-600 rounded-xl py-3 pl-4 pr-10 text-white text-sm focus:border-[#00f0ff] outline-none appearance-none shadow-inner transition-colors">
                            <option value="">-- Select Product --</option>
                            <?php foreach($products as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo $product_filter == $p['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-chevron-down absolute right-4 top-3.5 text-slate-500 pointer-events-none text-xs"></i>
                    </div>
                </div>
                
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Data Payload (1 per line)</label>
                    <textarea name="keys_input" rows="8" required placeholder="XXXX-XXXX-XXXX&#10;user@mail.com:pass123&#10;https://gift.link/..." 
                              class="w-full bg-slate-800/50 border border-slate-600 rounded-xl p-4 text-green-400 text-sm font-mono focus:border-[#00f0ff] outline-none shadow-inner transition-colors resize-none placeholder-slate-600"></textarea>
                </div>

                <div class="pt-2">
                    <button type="submit" name="add_keys" class="w-full bg-gradient-to-r from-blue-600 to-[#00f0ff] hover:from-blue-500 hover:to-[#00f0ff] text-slate-900 font-black py-3.5 rounded-xl shadow-[0_0_15px_rgba(0,240,255,0.3)] transition transform active:scale-95 flex justify-center items-center gap-2 uppercase tracking-widest text-xs">
                        <i class="fas fa-upload"></i> Execute Injection
                    </button>
                </div>
            </form>
        </div>

        <!-- Bulk Purge Action (Only show if a specific product is filtered) -->
        <?php if($product_filter > 0 && $stat_unsold > 0): ?>
        <div class="bg-red-900/20 border border-red-500/30 p-5 rounded-2xl shadow-lg relative overflow-hidden group">
            <div class="absolute right-0 top-0 p-4 opacity-10 group-hover:scale-110 transition duration-500"><i class="fas fa-radiation text-6xl text-red-500"></i></div>
            <h3 class="font-bold text-red-400 text-sm uppercase tracking-widest mb-2 relative z-10"><i class="fas fa-skull-crossbones mr-1"></i> Bulk Purge</h3>
            <p class="text-xs text-slate-400 mb-4 relative z-10">Remove all <strong><?php echo $stat_unsold; ?></strong> unsold assets for this specific product to clear bad batches.</p>
            <form method="POST" class="relative z-10" onsubmit="return confirm('CRITICAL WARNING: This will permanently delete all UNSOLD keys for this product. Proceed?');">
                <input type="hidden" name="target_product_id" value="<?php echo $product_filter; ?>">
                <button type="submit" name="clear_unsold" class="w-full bg-red-600/20 hover:bg-red-600 text-red-400 hover:text-white border border-red-500/50 py-2.5 rounded-lg text-xs font-bold transition flex items-center justify-center gap-2 uppercase tracking-wider">
                    <i class="fas fa-fire"></i> Purge Unsold Data
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <!-- RIGHT: INVENTORY LIST & FILTERS -->
    <div class="lg:col-span-2 flex flex-col h-full">
        
        <!-- Filters Area -->
        <div class="bg-slate-800/80 backdrop-blur p-5 rounded-2xl border border-slate-700 mb-6 shadow-lg shrink-0">
            <form method="GET" class="flex flex-col sm:flex-row gap-4 items-end">
                <input type="hidden" name="page" value="keys">
                
                <div class="flex-1 w-full">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Filter Matrix by Node</label>
                    <div class="relative">
                        <select name="product_id" class="w-full bg-slate-900 border border-slate-600 rounded-xl py-2.5 pl-3 pr-8 text-white text-sm focus:border-[#00f0ff] outline-none appearance-none cursor-pointer">
                            <option value="0">All Nodes (Global View)</option>
                            <?php foreach($products as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo $product_filter == $p['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-chevron-down absolute right-3 top-3 text-slate-500 pointer-events-none text-xs"></i>
                    </div>
                </div>

                <div class="w-full sm:w-48">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">State</label>
                    <div class="relative">
                        <select name="status" class="w-full bg-slate-900 border border-slate-600 rounded-xl py-2.5 pl-3 pr-8 text-white text-sm focus:border-[#00f0ff] outline-none appearance-none cursor-pointer">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="unsold" <?php echo $status_filter == 'unsold' ? 'selected' : ''; ?>>Available Only</option>
                            <option value="sold" <?php echo $status_filter == 'sold' ? 'selected' : ''; ?>>Distributed Only</option>
                        </select>
                        <i class="fas fa-chevron-down absolute right-3 top-3 text-slate-500 pointer-events-none text-xs"></i>
                    </div>
                </div>

                <button type="submit" class="bg-slate-700 hover:bg-slate-600 text-white font-bold py-2.5 px-6 rounded-xl transition text-sm shadow-md border border-slate-600 w-full sm:w-auto h-[42px] shrink-0">
                    Apply Filter
                </button>
            </form>
        </div>

        <!-- Data Table -->
        <div class="bg-slate-900/60 backdrop-blur rounded-2xl border border-slate-700 overflow-hidden shadow-2xl flex-grow flex flex-col">
            <div class="overflow-x-auto flex-grow custom-scrollbar">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-800/80 text-slate-400 uppercase text-[10px] font-bold tracking-widest sticky top-0 z-20 backdrop-blur-md">
                        <tr>
                            <th class="p-4 pl-6 w-1/2">Data Content (Key)</th>
                            <th class="p-4 w-1/4">Parent Node</th>
                            <th class="p-4 text-center">Status</th>
                            <th class="p-4 text-center">Trace</th>
                            <th class="p-4 text-right pr-6">Commands</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        <?php foreach($keys as $k): ?>
                            <tr class="hover:bg-slate-800/40 transition-colors group">
                                <!-- Key Content -->
                                <td class="p-4 pl-6 relative">
                                    <div class="font-mono text-xs break-all pr-8 <?php echo $k['is_sold'] ? 'text-slate-500 opacity-50 line-through' : 'text-green-400 font-bold'; ?>">
                                        <span id="key-text-<?php echo $k['id']; ?>"><?php echo htmlspecialchars($k['key_content']); ?></span>
                                    </div>
                                    <!-- Quick Copy Button -->
                                    <button onclick="navigator.clipboard.writeText(document.getElementById('key-text-<?php echo $k['id']; ?>').innerText); this.innerHTML='<i class=\'fas fa-check text-green-400\'></i>'; setTimeout(()=>this.innerHTML='<i class=\'fas fa-copy\'></i>', 1500);" 
                                            class="absolute right-2 top-1/2 -translate-y-1/2 w-6 h-6 rounded bg-slate-800 border border-slate-700 text-slate-400 flex items-center justify-center opacity-0 group-hover:opacity-100 hover:text-white transition-all shadow-sm" title="Copy Data">
                                        <i class="fas fa-copy text-[10px]"></i>
                                    </button>
                                </td>
                                
                                <!-- Product Name -->
                                <td class="p-4 text-slate-300 text-xs font-medium truncate max-w-[150px]" title="<?php echo htmlspecialchars($k['product_name']); ?>">
                                    <?php echo htmlspecialchars($k['product_name']); ?>
                                </td>
                                
                                <!-- Status Badge -->
                                <td class="p-4 text-center align-middle">
                                    <?php if($k['is_sold']): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded border bg-red-500/10 text-red-400 border-red-500/20 text-[9px] font-black uppercase tracking-wider">
                                            <i class="fas fa-lock text-[8px]"></i> Sold
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded border bg-green-500/10 text-green-400 border-green-500/20 text-[9px] font-black uppercase tracking-wider shadow-[0_0_10px_rgba(34,197,94,0.1)]">
                                            <i class="fas fa-unlock-alt text-[8px]"></i> Avail
                                        </span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Trace (Order ID) -->
                                <td class="p-4 text-center align-middle">
                                    <?php if($k['order_id']): ?>
                                        <a href="<?php echo admin_url('order_detail', ['id' => $k['order_id']]); ?>" class="inline-block bg-[#00f0ff]/10 text-[#00f0ff] hover:bg-[#00f0ff]/20 border border-[#00f0ff]/30 px-2 py-0.5 rounded text-[10px] font-mono font-bold transition-colors">#<?php echo $k['order_id']; ?></a>
                                    <?php else: ?>
                                        <span class="text-slate-600">-</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Action Commands -->
                                <td class="p-4 text-right pr-6 align-middle flex justify-end gap-2">
                                    <?php if(!$k['is_sold']): ?>
                                        <button type="button" onclick="openEditModal(<?php echo $k['id']; ?>, `<?php echo addslashes(htmlspecialchars($k['key_content'])); ?>`)" 
                                                class="w-8 h-8 rounded-lg bg-slate-800 border border-slate-700 text-blue-400 hover:text-white hover:bg-blue-600 hover:border-blue-500 transition-all shadow-sm flex items-center justify-center" title="Edit Data">
                                            <i class="fas fa-edit text-xs"></i>
                                        </button>
                                        <a href="<?php echo admin_url('keys', ['delete' => $k['id']]); ?>" 
                                           class="w-8 h-8 rounded-lg bg-slate-800 border border-slate-700 text-red-400 hover:text-white hover:bg-red-600 hover:border-red-500 transition-all shadow-sm flex items-center justify-center" 
                                           onclick="return confirm('Permanently delete this unsold asset?')" title="Purge Asset">
                                            <i class="fas fa-trash-alt text-xs"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="w-8 h-8 flex items-center justify-center text-slate-700 opacity-50 cursor-not-allowed" title="Asset locked (Sold)"><i class="fas fa-ban"></i></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if(empty($keys)): ?>
                            <tr>
                                <td colspan="5" class="p-12 text-center text-slate-500">
                                    <div class="w-16 h-16 bg-slate-800/50 rounded-full flex items-center justify-center mx-auto mb-3 border border-slate-700">
                                        <i class="fas fa-ghost text-2xl"></i>
                                    </div>
                                    <p class="font-medium tracking-wide">No assets match your current matrix query.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="p-3 border-t border-slate-700/80 bg-slate-800/50 text-center text-[10px] text-slate-500 uppercase tracking-widest font-bold shrink-0">
                Displaying recent <?php echo count($keys); ?> entries
            </div>
        </div>
    </div>
</div>

<!-- ===================================================================================== -->
<!-- EDIT KEY MODAL                                                                        -->
<!-- ===================================================================================== -->
<div id="editKeyModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-slate-950/80 backdrop-blur-sm" onclick="closeEditModal()"></div>
    
    <!-- Content -->
    <div class="bg-slate-900 border border-blue-500/30 rounded-2xl w-full max-w-lg relative z-10 shadow-[0_20px_60px_rgba(0,0,0,0.8)] transform scale-95 opacity-0 transition-all duration-300" id="editModalContent">
        
        <!-- Header -->
        <div class="p-5 border-b border-slate-700/80 flex justify-between items-center bg-slate-800/50 rounded-t-2xl">
            <h3 class="font-bold text-white flex items-center gap-2">
                <i class="fas fa-edit text-blue-400"></i> Modify Asset Data
            </h3>
            <button onclick="closeEditModal()" class="text-slate-400 hover:text-white transition w-8 h-8 flex items-center justify-center rounded-lg hover:bg-slate-700 border border-transparent hover:border-slate-600"><i class="fas fa-times"></i></button>
        </div>

        <form method="POST" class="p-6">
            <input type="hidden" name="key_id" id="edit_key_id" value="">
            
            <div class="mb-6">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Asset Payload</label>
                <textarea name="key_content" id="edit_key_content" rows="4" required 
                          class="w-full bg-slate-950 border border-slate-600 rounded-xl p-4 text-green-400 text-sm font-mono focus:border-blue-500 outline-none shadow-inner transition-colors resize-none"></textarea>
                <p class="text-[10px] text-yellow-500 mt-2 ml-1"><i class="fas fa-info-circle"></i> Warning: Editing an asset alters the data delivered to future buyers.</p>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeEditModal()" class="flex-1 bg-slate-800 hover:bg-slate-700 text-white font-bold py-3 rounded-xl border border-slate-600 transition text-sm">Cancel</button>
                <button type="submit" name="edit_key" class="flex-1 bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 rounded-xl shadow-lg shadow-blue-600/20 transition transform active:scale-95 text-sm flex justify-center items-center gap-2">
                    <i class="fas fa-save"></i> Apply Patch
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditModal(id, content) {
        document.getElementById('edit_key_id').value = id;
        
        // Decode HTML entities back to raw text for editing
        const txt = document.createElement("textarea");
        txt.innerHTML = content;
        document.getElementById('edit_key_content').value = txt.value;
        
        const modal = document.getElementById('editKeyModal');
        const mContent = document.getElementById('editModalContent');
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        setTimeout(() => {
            mContent.classList.remove('scale-95', 'opacity-0');
            mContent.classList.add('scale-100', 'opacity-100');
        }, 10);
    }

    function closeEditModal() {
        const modal = document.getElementById('editKeyModal');
        const mContent = document.getElementById('editModalContent');
        
        mContent.classList.remove('scale-100', 'opacity-100');
        mContent.classList.add('scale-95', 'opacity-0');
        
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }, 300);
    }
</script>