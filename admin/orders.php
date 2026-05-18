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

<div class="flex flex-col xl:flex-row justify-between items-start xl:items-end gap-6 mb-8 relative z-10 animate-fade-in-down">
    
    <div>
        <h1 class="text-3xl font-black text-white tracking-tight flex items-center gap-3">
            <i class="fas fa-satellite-dish text-[#00f0ff] animate-pulse"></i> Order Matrix
        </h1>
        <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mt-2">Manage & Authorize Customer Acquisitions</p>
    </div>
    
    <form method="GET" class="flex flex-col md:flex-row gap-4 w-full xl:w-auto bg-slate-900/60 backdrop-blur-xl p-3 rounded-2xl border border-[#00f0ff]/20 shadow-[0_0_20px_rgba(0,240,255,0.05)]">
        <input type="hidden" name="page" value="orders">
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
        
        <div class="relative w-full md:w-64 group">
            <i class="fas fa-search absolute left-4 top-3.5 text-slate-500 group-focus-within:text-[#00f0ff] transition-colors"></i>
            <input type="text" name="q" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="ID, User, or Txn..." 
                   class="bg-slate-950/50 border border-slate-700 rounded-xl pl-10 pr-4 py-2.5 text-sm text-white focus:border-[#00f0ff] focus:shadow-[0_0_15px_rgba(0,240,255,0.2)] outline-none w-full transition-all font-mono">
        </div>

        <div class="relative w-full md:w-48 group">
            <i class="fas fa-sort-amount-down absolute left-4 top-3.5 text-slate-500 group-focus-within:text-purple-400 transition-colors z-10 pointer-events-none"></i>
            <select name="sort" onchange="this.form.submit()" class="bg-slate-950/50 border border-slate-700 rounded-xl pl-10 pr-8 py-2.5 text-sm text-slate-300 focus:text-white focus:border-purple-400 focus:shadow-[0_0_15px_rgba(168,85,247,0.2)] outline-none w-full transition-all appearance-none cursor-pointer relative z-0">
                <option value="date_desc" <?php echo $sort_by == 'date_desc' ? 'selected' : ''; ?>>Newest First</option>
                <option value="date_asc" <?php echo $sort_by == 'date_asc' ? 'selected' : ''; ?>>Oldest First</option>
                <option value="name_asc" <?php echo $sort_by == 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                <option value="name_desc" <?php echo $sort_by == 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
            </select>
            <i class="fas fa-chevron-down absolute right-4 top-4 text-[10px] text-slate-500 pointer-events-none z-10"></i>
        </div>

        <button type="submit" class="md:hidden bg-[#00f0ff] text-slate-900 font-bold rounded-xl py-2 shadow-[0_0_15px_rgba(0,240,255,0.3)]">Filter Matrix</button>
    </form>
</div>

<div class="flex gap-2 sm:gap-4 mb-6 overflow-x-auto custom-scrollbar pb-2 hide-scrollbar">
    <a href="<?php echo build_matrix_url(['status' => 'all', 'p' => 1]); ?>" 
       class="relative px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all duration-300 shrink-0 <?php echo $status_filter == 'all' ? 'bg-[#00f0ff]/10 text-[#00f0ff] border border-[#00f0ff]/50 shadow-[0_0_15px_rgba(0,240,255,0.2)]' : 'bg-slate-900/50 text-slate-500 hover:text-slate-300 border border-transparent hover:border-slate-700'; ?>">
        <i class="fas fa-globe mr-1.5"></i> All Transmissions
    </a>
    
    <a href="<?php echo build_matrix_url(['status' => 'pending', 'p' => 1]); ?>" 
       class="relative px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all duration-300 shrink-0 <?php echo $status_filter == 'pending' ? 'bg-yellow-500/10 text-yellow-400 border border-yellow-500/50 shadow-[0_0_15px_rgba(234,179,8,0.2)]' : 'bg-slate-900/50 text-slate-500 hover:text-yellow-500/50 border border-transparent hover:border-slate-700'; ?>">
        <i class="fas fa-hourglass-half mr-1.5 <?php echo $status_filter == 'pending' ? 'animate-pulse' : ''; ?>"></i> Awaiting Auth
    </a>
    
    <a href="<?php echo build_matrix_url(['status' => 'active', 'p' => 1]); ?>" 
       class="relative px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all duration-300 shrink-0 <?php echo $status_filter == 'active' ? 'bg-green-500/10 text-green-400 border border-green-500/50 shadow-[0_0_15px_rgba(34,197,94,0.2)]' : 'bg-slate-900/50 text-slate-500 hover:text-green-500/50 border border-transparent hover:border-slate-700'; ?>">
        <i class="fas fa-check-circle mr-1.5"></i> Active / Completed
    </a>
    
    <a href="<?php echo build_matrix_url(['status' => 'rejected', 'p' => 1]); ?>" 
       class="relative px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all duration-300 shrink-0 <?php echo $status_filter == 'rejected' ? 'bg-red-500/10 text-red-400 border border-red-500/50 shadow-[0_0_15px_rgba(239,68,68,0.2)]' : 'bg-slate-900/50 text-slate-500 hover:text-red-500/50 border border-transparent hover:border-slate-700'; ?>">
        <i class="fas fa-times-circle mr-1.5"></i> Terminated
    </a>
</div>

<div class="bg-slate-900/80 backdrop-blur-2xl rounded-3xl border border-[#00f0ff]/20 overflow-hidden shadow-[0_20px_50px_rgba(0,0,0,0.5)] relative z-10">
    <div class="overflow-x-auto custom-scrollbar">
        <table class="w-full text-left text-sm whitespace-nowrap">
            <thead class="bg-slate-950/80 text-slate-400 uppercase text-[10px] tracking-widest font-black border-b border-slate-700/50">
                <tr>
                    <th class="p-5 pl-6">Order ID</th>
                    <th class="p-5">Operative</th>
                    <th class="p-5">Target Asset</th>
                    <th class="p-5">Txn Hash</th>
                    <th class="p-5">Value</th>
                    <th class="p-5">Status</th>
                    <th class="p-5 text-right pr-6">Command</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/80">
                <?php foreach($orders as $o): 
                    $isPass = !empty($o['pass_id']);
                ?>
                    <tr class="hover:bg-slate-800/50 transition-colors duration-300 group">
                        
                        <td class="p-5 pl-6">
                            <span class="text-[#00f0ff] font-mono font-bold bg-[#00f0ff]/10 px-2.5 py-1 rounded-md border border-[#00f0ff]/20">#<?php echo $o['id']; ?></span>
                            <div class="text-[9px] text-slate-500 mt-2 font-mono"><i class="far fa-clock"></i> <?php echo date('M d, H:i', strtotime($o['created_at'])); ?></div>
                        </td>
                        
                        <td class="p-5">
                            <div class="font-bold text-white group-hover:text-[#00f0ff] transition-colors flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full bg-slate-700 flex items-center justify-center text-[10px] text-slate-300 border border-slate-600">
                                    <?php echo strtoupper(substr($o['username'], 0, 1)); ?>
                                </div>
                                @<?php echo htmlspecialchars($o['username']); ?>
                            </div>
                        </td>
                        
                        <td class="p-5">
                            <div class="flex items-center gap-2">
                                <span class="font-bold <?php echo $isPass ? 'text-yellow-400' : 'text-slate-200'; ?> truncate max-w-[200px]">
                                    <?php echo htmlspecialchars($o['product_name']); ?>
                                </span>
                                <?php if($isPass): ?>
                                    <span class="text-[8px] bg-yellow-500/20 text-yellow-500 px-1.5 py-0.5 rounded border border-yellow-500/30 uppercase font-black tracking-widest shadow-sm"><i class="fas fa-crown"></i> Pass</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        
                        <td class="p-5">
                            <code class="text-yellow-400 font-bold bg-yellow-500/10 px-2 py-1 rounded border border-yellow-500/20 select-all">
                                <?php echo htmlspecialchars($o['transaction_last_6']); ?>
                            </code>
                        </td>
                        
                        <td class="p-5 font-mono font-bold text-green-400">
                            <?php echo format_admin_currency($o['total_price_paid']); ?>
                        </td>
                        
                        <td class="p-5">
                            <?php 
                                // Liquid Glass Status Badges
                                $status_html = match($o['status']) {
                                    'pending' => '<span class="bg-yellow-500/20 text-yellow-400 border border-yellow-500/50 px-2.5 py-1 rounded text-[9px] font-black uppercase tracking-widest shadow-[0_0_10px_rgba(234,179,8,0.2)] animate-pulse"><i class="fas fa-hourglass-half mr-1"></i> Pending</span>',
                                    'active', 'completed' => '<span class="bg-green-500/20 text-green-400 border border-green-500/50 px-2.5 py-1 rounded text-[9px] font-black uppercase tracking-widest shadow-[0_0_10px_rgba(34,197,94,0.2)]"><i class="fas fa-check mr-1"></i> Active</span>',
                                    'rejected' => '<span class="bg-red-500/20 text-red-400 border border-red-500/50 px-2.5 py-1 rounded text-[9px] font-black uppercase tracking-widest shadow-[0_0_10px_rgba(239,68,68,0.2)]"><i class="fas fa-times mr-1"></i> Terminated</span>',
                                    default => '<span class="bg-slate-500/20 text-slate-400 border border-slate-500/50 px-2.5 py-1 rounded text-[9px] font-black uppercase tracking-widest">'.$o['status'].'</span>'
                                };
                                echo $status_html;
                            ?>
                        </td>
                        
                        <td class="p-5 text-right pr-6">
                            <a href="<?php echo admin_url('order_detail', ['id' => $o['id']]); ?>" 
                               class="inline-flex items-center gap-2 bg-slate-800 hover:bg-[#00f0ff] text-[#00f0ff] hover:text-slate-900 px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all duration-300 border border-[#00f0ff]/30 hover:border-transparent hover:shadow-[0_0_15px_rgba(0,240,255,0.4)]">
                                Terminal <i class="fas fa-arrow-right"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                
                <?php if(empty($orders)): ?>
                    <tr>
                        <td colspan="7" class="p-16 text-center text-slate-500 relative overflow-hidden">
                            <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PHBhdHRlcm4gaWQ9ImdyaWQiIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCIgcGF0dGVyblVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+PHBhdGggZD0iTSAwIDEwIEwgNDAgMTAgTSAxMCAwIEwgMTAgNDAiIGZpbGw9Im5vbmUiIHN0cm9rZT0icmdiYSgyNTUsIDI1NSwgMjU1LCAwLjAyKSIgc3Ryb2tlLXdpZHRoPSIxIi8+PC9wYXR0ZXJuPjwvZGVmcz48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSJ1cmwoI2dyaWQpIi8+PC9zdmc+')] opacity-50 pointer-events-none"></div>
                            <div class="relative z-10">
                                <div class="w-20 h-20 bg-slate-900 rounded-full flex items-center justify-center mx-auto mb-4 border border-slate-700 shadow-inner">
                                    <i class="fas fa-ghost text-3xl text-slate-600"></i>
                                </div>
                                <h3 class="text-white font-bold text-lg mb-1 tracking-tight">Matrix Empty</h3>
                                <p class="text-xs font-mono">No telemetry found matching your exact parameters.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if($total_pages > 1): ?>
        <div class="p-4 border-t border-slate-700/80 bg-slate-950/50 flex flex-col sm:flex-row items-center justify-between gap-4">
            
            <div class="text-xs text-slate-500 font-mono font-bold uppercase tracking-widest">
                Displaying <span class="text-[#00f0ff]"><?php echo $offset + 1; ?></span> - <span class="text-[#00f0ff]"><?php echo min($offset + $limit, $total_records); ?></span> of <span class="text-white"><?php echo $total_records; ?></span> Transmissions
            </div>
            
            <div class="flex items-center gap-1.5">
                <?php if($page_num > 1): ?>
                    <a href="<?php echo build_matrix_url(['p' => $page_num - 1]); ?>" class="w-8 h-8 rounded-lg bg-slate-800 text-slate-400 hover:text-[#00f0ff] hover:bg-slate-700 border border-slate-600 transition-colors flex items-center justify-center shadow-sm">
                        <i class="fas fa-chevron-left text-xs"></i>
                    </a>
                <?php else: ?>
                    <span class="w-8 h-8 rounded-lg bg-slate-900 text-slate-700 border border-slate-800 flex items-center justify-center cursor-not-allowed">
                        <i class="fas fa-chevron-left text-xs"></i>
                    </span>
                <?php endif; ?>

                <div class="flex items-center gap-1 mx-2">
                    <?php 
                    $start_page = max(1, $page_num - 2);
                    $end_page = min($total_pages, $page_num + 2);
                    
                    if($start_page > 1) {
                        echo '<a href="'.build_matrix_url(['p'=>1]).'" class="w-8 h-8 flex items-center justify-center text-xs font-bold rounded-lg text-slate-400 hover:text-white transition">1</a>';
                        if($start_page > 2) echo '<span class="text-slate-600 text-xs px-1">...</span>';
                    }
                    
                    for($i = $start_page; $i <= $end_page; $i++): 
                        if($i == $page_num): ?>
                            <span class="w-8 h-8 rounded-lg bg-[#00f0ff] text-slate-900 font-black flex items-center justify-center text-xs shadow-[0_0_10px_rgba(0,240,255,0.4)]"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="<?php echo build_matrix_url(['p' => $i]); ?>" class="w-8 h-8 rounded-lg bg-slate-800/50 text-slate-400 hover:text-[#00f0ff] hover:bg-slate-800 border border-slate-700 transition-colors flex items-center justify-center text-xs font-bold">
                                <?php echo $i; ?>
                            </a>
                    <?php endif; endfor; 
                    
                    if($end_page < $total_pages) {
                        if($end_page < $total_pages - 1) echo '<span class="text-slate-600 text-xs px-1">...</span>';
                        echo '<a href="'.build_matrix_url(['p'=>$total_pages]).'" class="w-8 h-8 flex items-center justify-center text-xs font-bold rounded-lg text-slate-400 hover:text-white transition">'.$total_pages.'</a>';
                    }
                    ?>
                </div>

                <?php if($page_num < $total_pages): ?>
                    <a href="<?php echo build_matrix_url(['p' => $page_num + 1]); ?>" class="w-8 h-8 rounded-lg bg-slate-800 text-slate-400 hover:text-[#00f0ff] hover:bg-slate-700 border border-slate-600 transition-colors flex items-center justify-center shadow-sm">
                        <i class="fas fa-chevron-right text-xs"></i>
                    </a>
                <?php else: ?>
                    <span class="w-8 h-8 rounded-lg bg-slate-900 text-slate-700 border border-slate-800 flex items-center justify-center cursor-not-allowed">
                        <i class="fas fa-chevron-right text-xs"></i>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>