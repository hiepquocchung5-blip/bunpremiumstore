<?php
// admin/orders.php
// PRODUCTION v6.0 - Liquid Glass UI, Telemetry Sorting & Pagination Matrix

// 1. Core Variables & Filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'date_desc';
$page_num = max(1, isset($_GET['p']) ? (int)$_GET['p'] : 1);
$limit = 15; // Orders per page
$offset = ($page_num - 1) * $limit;

$where = [];
$params = [];

// Status Filter
if ($status_filter !== 'all') {
    $where[] = "o.status = ?";
    $params[] = $status_filter;
}

// Search Filter
if ($search_query) {
    $where[] = "(o.id LIKE ? OR u.username LIKE ? OR o.transaction_last_6 LIKE ?)";
    $term = "%$search_query%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$where_sql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// 2. Sorting Logic
$order_sql = match($sort_by) {
    'name_asc'  => 'product_name ASC',
    'name_desc' => 'product_name DESC',
    'date_asc'  => 'o.created_at ASC',
    'date_desc' => 'o.created_at DESC',
    default     => 'o.created_at DESC'
};

// 3. Pagination Telemetry (Count Total)
$count_sql = "
    SELECT COUNT(*) 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    LEFT JOIN products p ON o.product_id = p.id 
    LEFT JOIN passes ps ON o.pass_id = ps.id
    $where_sql
";
$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($params);
$total_records = $stmt_count->fetchColumn();
$total_pages = max(1, ceil($total_records / $limit));

// Ensure page_num doesn't exceed total_pages
if ($page_num > $total_pages) {
    $page_num = $total_pages;
    $offset = ($page_num - 1) * $limit;
}

// 4. Fetch Matrix Data
$sql = "
    SELECT o.*, u.username, 
           COALESCE(p.name, ps.name) as product_name 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    LEFT JOIN products p ON o.product_id = p.id 
    LEFT JOIN passes ps ON o.pass_id = ps.id
    $where_sql
    ORDER BY $order_sql
    LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// URL Builder Helper for UI Links
function build_matrix_url($updates) {
    $get = $_GET;
    foreach($updates as $k => $v) $get[$k] = $v;
    return '?' . http_build_query($get);
}
?>

<div class="mb-12 flex flex-col md:flex-row justify-between items-start md:items-end gap-8 relative z-10 animate-fade-in">
    <div>
        <h1 class="text-3xl md:text-5xl font-extrabold text-white tracking-tight font-heading">Transactions <span class="text-indigo-500">.</span></h1>
        <p class="text-slate-500 text-sm mt-3 leading-relaxed max-w-md">Review and manage all customer purchase records and fulfillment statuses.</p>
    </div>
    
    <form method="GET" class="flex flex-col md:flex-row gap-4 w-full xl:w-auto bg-black/20 p-4 rounded-[2rem] border border-white/5 shadow-sm backdrop-blur-sm">
        <input type="hidden" name="page" value="orders">
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
        
        <div class="relative w-full md:w-72 group">
            <i class="fas fa-search absolute left-5 top-1/2 -translate-y-1/2 text-slate-500 group-focus-within:text-indigo-400 transition-colors"></i>
            <input type="text" name="q" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search ID, User, or Txn..." 
                   class="bg-slate-900/50 border border-white/5 rounded-2xl pl-12 pr-4 py-3.5 text-sm text-white focus:border-indigo-500 outline-none w-full transition-all placeholder-slate-600">
        </div>

        <div class="relative w-full md:w-56 group">
            <i class="fas fa-arrow-down-wide-short absolute left-5 top-1/2 -translate-y-1/2 text-slate-500 transition-colors z-10 pointer-events-none"></i>
            <select name="sort" onchange="this.form.submit()" class="bg-slate-900/50 border border-white/5 rounded-2xl pl-12 pr-10 py-3.5 text-sm text-slate-400 focus:text-white focus:border-indigo-500 outline-none w-full transition-all appearance-none cursor-pointer relative z-0 font-medium">
                <option value="date_desc" <?php echo $sort_by == 'date_desc' ? 'selected' : ''; ?>>Newest Activity</option>
                <option value="date_asc" <?php echo $sort_by == 'date_asc' ? 'selected' : ''; ?>>Oldest Activity</option>
                <option value="name_asc" <?php echo $sort_by == 'name_asc' ? 'selected' : ''; ?>>Asset Name (A-Z)</option>
                <option value="name_desc" <?php echo $sort_by == 'name_desc' ? 'selected' : ''; ?>>Asset Name (Z-A)</option>
            </select>
            <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-[9px] text-slate-600 pointer-events-none z-10"></i>
        </div>

        <button type="submit" class="md:hidden bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-2xl py-3.5 shadow-lg shadow-indigo-600/20 active:scale-95 transition-all uppercase tracking-widest text-[10px]">Refresh Results</button>
    </form>
</div>

<div class="flex gap-4 mb-10 overflow-x-auto custom-scrollbar pb-4 hide-scrollbar">
    <a href="<?php echo build_matrix_url(['status' => 'all', 'p' => 1]); ?>" 
       class="relative px-8 py-3.5 rounded-2xl text-[11px] font-bold uppercase tracking-[0.15em] transition-all duration-300 shrink-0 <?php echo $status_filter == 'all' ? 'bg-indigo-600 text-white shadow-xl shadow-indigo-600/20' : 'bg-slate-800/40 text-slate-400 hover:text-white border border-white/5'; ?>">
        <i class="fas fa-layer-group mr-2 opacity-70"></i> All Records
    </a>
    
    <a href="<?php echo build_matrix_url(['status' => 'pending', 'p' => 1]); ?>" 
       class="relative px-8 py-3.5 rounded-2xl text-[11px] font-bold uppercase tracking-[0.15em] transition-all duration-300 shrink-0 <?php echo $status_filter == 'pending' ? 'bg-amber-500 text-black shadow-xl shadow-amber-500/20 font-extrabold' : 'bg-slate-800/40 text-slate-400 hover:text-white border border-white/5'; ?>">
        <i class="fas fa-clock mr-2 <?php echo $status_filter == 'pending' ? 'animate-pulse' : 'opacity-70'; ?>"></i> Pending Fulfillment
    </a>
    
    <a href="<?php echo build_matrix_url(['status' => 'active', 'p' => 1]); ?>" 
       class="relative px-8 py-3.5 rounded-2xl text-[11px] font-bold uppercase tracking-[0.15em] transition-all duration-300 shrink-0 <?php echo $status_filter == 'active' ? 'bg-emerald-500 text-black shadow-xl shadow-emerald-500/20 font-extrabold' : 'bg-slate-800/40 text-slate-400 hover:text-white border border-white/5'; ?>">
        <i class="fas fa-circle-check mr-2 opacity-70"></i> Successful
    </a>
    
    <a href="<?php echo build_matrix_url(['status' => 'rejected', 'p' => 1]); ?>" 
       class="relative px-8 py-3.5 rounded-2xl text-[11px] font-bold uppercase tracking-[0.15em] transition-all duration-300 shrink-0 <?php echo $status_filter == 'rejected' ? 'bg-rose-500 text-white shadow-xl shadow-rose-500/20 font-extrabold' : 'bg-slate-800/40 text-slate-400 hover:text-white border border-white/5'; ?>">
        <i class="fas fa-circle-xmark mr-2 opacity-70"></i> Revoked / Cancelled
    </a>
</div>

<div class="custom-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm whitespace-nowrap">
            <thead>
                <tr class="bg-black/20 text-slate-500 uppercase text-[10px] tracking-[0.2em] font-bold">
                    <th class="p-6 pl-10">Transaction</th>
                    <th class="p-6">Customer Profile</th>
                    <th class="p-6">Purchased Asset</th>
                    <th class="p-6">Reference ID</th>
                    <th class="p-6">Settlement</th>
                    <th class="p-6">Status</th>
                    <th class="p-6 pr-10 text-right">Interaction</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/[0.03]">
                <?php foreach($orders as $o): 
                    $isPass = !empty($o['pass_id']);
                ?>
                    <tr class="hover:bg-indigo-500/[0.02] transition-colors duration-300 group">
                        
                        <td class="p-6 pl-10">
                            <div class="flex flex-col gap-1.5">
                                <span class="bg-slate-800/50 text-slate-400 px-3 py-1.5 rounded-xl font-mono text-[10px] border border-white/5 w-fit">#<?php echo $o['id']; ?></span>
                                <div class="text-[9px] text-slate-500 font-bold uppercase tracking-widest flex items-center gap-1.5 ml-1">
                                    <i class="far fa-clock opacity-70"></i> 
                                    <?php echo date('M d, Y • H:i', strtotime($o['created_at'])); ?>
                                </div>
                            </div>
                        </td>
                        
                        <td class="p-6">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-indigo-500/10 to-purple-600/10 border border-white/5 flex items-center justify-center text-xs font-bold text-indigo-400 group-hover:scale-110 transition-transform">
                                    <?php echo strtoupper(substr($o['username'], 0, 1)); ?>
                                </div>
                                <div class="flex flex-col">
                                    <span class="font-bold text-white group-hover:text-indigo-400 transition-colors">@<?php echo htmlspecialchars($o['username']); ?></span>
                                    <span class="text-[9px] text-slate-600 uppercase font-bold tracking-widest">Client</span>
                                </div>
                            </div>
                        </td>
                        
                        <td class="p-6">
                            <div class="flex items-center gap-3">
                                <span class="font-bold <?php echo $isPass ? 'text-amber-400' : 'text-slate-300'; ?> truncate max-w-[200px] text-[13px]">
                                    <?php echo htmlspecialchars($o['product_name']); ?>
                                </span>
                                <?php if($isPass): ?>
                                    <span class="text-[8px] bg-amber-500/10 text-amber-500 px-2 py-0.5 rounded-lg border border-amber-500/20 uppercase font-bold tracking-widest shadow-sm"><i class="fas fa-crown text-[7px]"></i> Pass</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        
                        <td class="p-6">
                            <div class="flex items-center gap-2">
                                <span class="text-[11px] font-mono font-bold text-slate-400 bg-black/20 px-3 py-1.5 rounded-xl border border-white/5 select-all hover:text-indigo-400 transition-colors">
                                    <?php echo htmlspecialchars($o['transaction_last_6']); ?>
                                </span>
                            </div>
                        </td>
                        
                        <td class="p-6">
                            <span class="font-extrabold text-white tracking-tight">
                                <?php echo format_admin_currency($o['total_price_paid']); ?>
                            </span>
                        </td>
                        
                        <td class="p-6">
                            <?php 
                                $statusClass = match($o['status']) {
                                    'completed', 'active' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                    'pending' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                    'cancelled', 'rejected' => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
                                    default => 'bg-slate-500/10 text-slate-400 border-slate-500/20'
                                };
                            ?>
                            <span class="px-4 py-2 rounded-xl text-[9px] font-bold uppercase tracking-[0.15em] border <?php echo $statusClass; ?> shadow-sm">
                                <?php echo $o['status']; ?>
                            </span>
                        </td>
                        
                        <td class="p-6 pr-10 text-right">
                            <a href="<?php echo admin_url('order_detail', ['id' => $o['id']]); ?>" 
                               class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-slate-800/50 border border-white/5 text-indigo-400 hover:bg-indigo-600 hover:text-white hover:border-indigo-500 transition-all">
                                <i class="fas fa-arrow-right text-xs"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                
                <?php if(empty($orders)): ?>
                    <tr>
                        <td colspan="7" class="p-24 text-center">
                            <div class="flex flex-col items-center opacity-30">
                                <div class="w-20 h-20 bg-slate-800 rounded-3xl flex items-center justify-center mb-6">
                                    <i class="fas fa-file-invoice text-4xl text-slate-600"></i>
                                </div>
                                <h3 class="text-white font-bold text-xl font-heading tracking-tight uppercase">No Records Identified</h3>
                                <p class="text-sm mt-2 font-medium text-slate-500">The current search criteria yielded zero transactions.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if($total_pages > 1): ?>
        <div class="p-8 border-t border-white/5 bg-black/10 flex flex-col sm:flex-row items-center justify-between gap-8">
            
            <div class="text-[10px] text-slate-500 font-bold uppercase tracking-[0.2em]">
                Viewing <span class="text-white font-black"><?php echo $offset + 1; ?></span> to <span class="text-white font-black"><?php echo min($offset + $limit, $total_records); ?></span> <span class="mx-1">•</span> Total <span class="text-indigo-400 font-black"><?php echo $total_records; ?></span>
            </div>
            
            <div class="flex items-center gap-3">
                <?php if($page_num > 1): ?>
                    <a href="<?php echo build_matrix_url(['p' => $page_num - 1]); ?>" class="w-11 h-11 rounded-xl bg-slate-800/50 hover:bg-indigo-600 text-slate-400 hover:text-white border border-white/5 hover:border-indigo-500 transition-all flex items-center justify-center shadow-sm">
                        <i class="fas fa-chevron-left text-xs"></i>
                    </a>
                <?php else: ?>
                    <span class="w-11 h-11 rounded-xl bg-slate-900/50 text-slate-700 border border-white/5 flex items-center justify-center cursor-not-allowed">
                        <i class="fas fa-chevron-left text-xs"></i>
                    </span>
                <?php endif; ?>

                <div class="flex items-center gap-2">
                    <?php 
                    $start_page = max(1, $page_num - 2);
                    $end_page = min($total_pages, $page_num + 2);
                    
                    if($start_page > 1) {
                        echo '<a href="'.build_matrix_url(['p'=>1]).'" class="w-11 h-11 flex items-center justify-center text-xs font-bold rounded-xl text-slate-400 hover:text-indigo-400 transition-all border border-transparent hover:border-white/5">1</a>';
                        if($start_page > 2) echo '<span class="text-slate-700 text-xs px-1 tracking-widest">...</span>';
                    }
                    
                    for($i = $start_page; $i <= $end_page; $i++): 
                        if($i == $page_num): ?>
                            <span class="w-11 h-11 rounded-xl bg-indigo-600 text-white font-black flex items-center justify-center text-xs shadow-xl shadow-indigo-600/20 border border-indigo-500"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="<?php echo build_matrix_url(['p' => $i]); ?>" class="w-11 h-11 rounded-xl bg-slate-800/50 text-slate-400 hover:text-indigo-400 hover:bg-slate-700/50 border border-white/5 transition-all flex items-center justify-center text-xs font-bold">
                                <?php echo $i; ?>
                            </a>
                    <?php endif; endfor; 
                    
                    if($end_page < $total_pages) {
                        if($end_page < $total_pages - 1) echo '<span class="text-slate-700 text-xs px-1 tracking-widest">...</span>';
                        echo '<a href="'.build_matrix_url(['p'=>$total_pages]).'" class="w-11 h-11 flex items-center justify-center text-xs font-bold rounded-xl text-slate-400 hover:text-indigo-400 transition-all border border-transparent hover:border-white/5">'.$total_pages.'</a>';
                    }
                    ?>
                </div>

                <?php if($page_num < $total_pages): ?>
                    <a href="<?php echo build_matrix_url(['p' => $page_num + 1]); ?>" class="w-11 h-11 rounded-xl bg-slate-800/50 hover:bg-indigo-600 text-slate-400 hover:text-white border border-white/5 hover:border-indigo-500 transition-all flex items-center justify-center shadow-sm">
                        <i class="fas fa-chevron-right text-xs"></i>
                    </a>
                <?php else: ?>
                    <span class="w-11 h-11 rounded-xl bg-slate-900/50 text-slate-700 border border-white/5 flex items-center justify-center cursor-not-allowed">
                        <i class="fas fa-chevron-right text-xs"></i>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>