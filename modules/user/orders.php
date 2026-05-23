<?php
// modules/user/orders.php
// PRODUCTION DEPLOYMENT v5.0 - Immersive Mobile Chat, Strict Scrolling & 3-Stage Receipts

if (!is_logged_in()) redirect('index.php?module=auth&page=login');

$user_id = $_SESSION['user_id'];
$active_chat_id = isset($_GET['view_chat']) ? (int)$_GET['view_chat'] : 0;

// =====================================================================================
// 1. AJAX ENDPOINTS (Polling & Message Sending)
// =====================================================================================

// A. Handle Incoming Chat Message (AJAX POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_msg'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    
    // Anti-Spam: 1-second transmission cooldown
    if (isset($_SESSION['last_msg_time']) && time() - $_SESSION['last_msg_time'] < 1) {
        echo json_encode(['success' => false, 'error' => 'Please wait a moment before sending another message.']);
        exit;
    }
    
    if (hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $msg = trim($_POST['message']);
        $oid = (int)$_POST['order_id'];
        
        $stmt = $pdo->prepare("
            SELECT o.status, u.username, COALESCE(p.name, ps.name) as item_name 
            FROM orders o 
            JOIN users u ON o.user_id = u.id
            LEFT JOIN products p ON o.product_id = p.id
            LEFT JOIN passes ps ON o.pass_id = ps.id
            WHERE o.id = ? AND o.user_id = ?
        ");
        $stmt->execute([$oid, $user_id]);
        $order_check = $stmt->fetch();
        
        if ($order_check && $msg !== '' && $order_check['status'] !== 'rejected') {
            try {
                $pdo->exec("SET NAMES 'utf8mb4'");
                $stmt = $pdo->prepare("INSERT INTO order_messages (order_id, sender_type, message) VALUES (?, 'user', ?)");
                $stmt->execute([$oid, $msg]);
                
                $_SESSION['last_msg_time'] = time(); // Record transmission
                
                // ⚡️ INVALIDATE CACHE for real-time notification update
                invalidate_user_cache($user_id);
                
                // ⚡️ REAL-TIME TELEGRAM ALERT TO ADMINS
                if (defined('TG_BOT_TOKEN') && defined('TG_ADMIN_CHAT_ID')) {
                    $admin_url = defined('ADMIN_URL') ? ADMIN_URL : BASE_URL . 'admin/';
                    $tg_msg = "💬 <b>New Customer Message</b>\n\n";
                    $tg_msg .= "🆔 <b>Order:</b> #{$oid} - {$order_check['item_name']}\n";
                    $tg_msg .= "👤 <b>User:</b> @{$order_check['username']}\n";
                    $tg_msg .= "💬 <b>Message:</b> <i>" . htmlspecialchars($msg) . "</i>\n\n";
                    $tg_msg .= "⚡️ <a href='{$admin_url}index.php?page=order_detail&id={$oid}'>Reply in Admin Panel</a>";

                    $admin_ids = array_map('trim', explode(',', TG_ADMIN_CHAT_ID));
                    foreach ($admin_ids as $adid) {
                        if (empty($adid)) continue;
                        $ch = curl_init("https://api.telegram.org/bot" . TG_BOT_TOKEN . "/sendMessage");
                        curl_setopt_array($ch, [
                            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false,
                            CURLOPT_POSTFIELDS => ['chat_id' => $adid, 'text' => $tg_msg, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true]
                        ]);
                        curl_exec($ch); curl_close($ch);
                    }
                }
                
                echo json_encode(['success' => true]);
                exit;
            } catch (Exception $e) {}
        }
    }
    echo json_encode(['success' => false, 'error' => 'Message could not be sent.']);
    exit;
}

// B. Handle Live Chat Polling (AJAX GET) - EXTREME LATENCY REDUCTION VIA ETAGS
if (isset($_GET['ajax']) && $_GET['ajax'] == 1 && $active_chat_id > 0) {
    if (ob_get_length()) ob_clean();

    $stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$active_chat_id, $user_id]);
    if (!$stmt->fetch()) { http_response_code(403); exit; }

    // Micro-latency check for ETags
    $stmt_latest = $pdo->prepare("SELECT MAX(id) FROM order_messages WHERE order_id = ?");
    $stmt_latest->execute([$active_chat_id]);
    $latest_id = $stmt_latest->fetchColumn() ?: '0';

    $etag = '"' . md5('chat_' . $active_chat_id . '_' . $latest_id) . '"';

    header("Cache-Control: private, max-age=0, must-revalidate");
    header("ETag: $etag");

    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
        http_response_code(304); // Not Modified
        exit;
    }

    // Determine "Seen" status by finding the latest Admin reply ID
    $stmt_seen = $pdo->prepare("SELECT MAX(id) FROM order_messages WHERE order_id = ? AND sender_type = 'admin'");
    $stmt_seen->execute([$active_chat_id]);
    $latest_admin_id = $stmt_seen->fetchColumn() ?: 0;

    // Fetch and render messages
    $stmt = $pdo->prepare("SELECT * FROM order_messages WHERE order_id = ? ORDER BY created_at ASC");
    $stmt->execute([$active_chat_id]);
    $messages = $stmt->fetchAll();
    
    foreach($messages as $msg) {
        $is_user = $msg['sender_type'] === 'user';
        $align = $is_user ? 'justify-end' : 'justify-start';
        $item_align = $is_user ? 'items-end' : 'items-start';
        
        $bubble_bg = $is_user 
            ? 'bg-gradient-to-br from-[#00f0ff] to-blue-600 text-slate-900 font-bold rounded-2xl rounded-br-sm shadow-[0_4px_20px_rgba(0,240,255,0.2)]' 
            : 'bg-slate-800 text-slate-200 border border-slate-700 rounded-2xl rounded-bl-sm shadow-lg';
            
        $time = date('h:i A', strtotime($msg['created_at']));
        $safe_msg = htmlspecialchars($msg['message']);

        echo "<div class='flex w-full {$align} mb-5 animate-fade-in-up group' data-msg-id='{$msg['id']}'>";
        echo "<div class='max-w-[85%] md:max-w-[70%] flex flex-col {$item_align}'>";
        echo "<div class='px-4 py-3.5 text-[13px] md:text-sm relative {$bubble_bg} transition-transform group-hover:scale-[1.01]'>";
        
        if ($msg['is_credential']) {
            echo "<div class='flex items-center gap-2 text-[10px] font-black " . ($is_user ? "text-slate-900" : "text-[#00f0ff]") . " mb-2 border-b " . ($is_user ? "border-slate-900/20" : "border-white/10") . " pb-1.5 uppercase tracking-wider'><i class='fas fa-shield-alt'></i> Secure Data Proxy</div>";
            echo "<div class='font-mono text-xs whitespace-pre-wrap select-all " . ($is_user ? "bg-white/30 text-slate-900" : "bg-black/40 text-green-400") . " p-3 rounded-xl border border-white/10'>{$safe_msg}</div>";
        } else {
            echo "<div class='whitespace-pre-wrap break-words leading-relaxed'>{$safe_msg}</div>";
        }
        
        echo "</div>";
        
        // Premium Read Receipts
        echo "<div class='flex items-center gap-2 mt-1.5 px-1 opacity-60 group-hover:opacity-100 transition-opacity'>";
        if ($is_user) {
            if ($msg['id'] < $latest_admin_id) {
                echo "<i class='fas fa-check-double text-[10px] text-[#00f0ff] drop-shadow-[0_0_8px_rgba(0,240,255,1)]' title='Seen'></i>";
            } else {
                echo "<i class='fas fa-check-double text-[10px] text-slate-500' title='Delivered'></i>";
            }
        }
        echo "<span class='text-[10px] text-slate-500 font-mono font-bold tracking-tight'>{$time}</span>";
        echo "</div>";
        
        echo "</div></div>";
    }
    exit;
}

// C. Fallback Standard POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && !isset($_POST['ajax_msg'])) {
    if (hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $msg = trim($_POST['message']);
        $oid = (int)$_POST['order_id'];
        $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ? AND user_id = ?");
        $stmt->execute([$oid, $user_id]);
        if ($stmt->fetch() && $msg !== '') {
            $pdo->prepare("INSERT INTO order_messages (order_id, sender_type, message) VALUES (?, 'user', ?)")->execute([$oid, $msg]);
            invalidate_user_cache($user_id);
        }
        redirect("index.php?module=user&page=orders&view_chat=" . $oid);
    }
}

// =====================================================================================
// 2. NORMAL PAGE LOAD & UI RENDER
// =====================================================================================

$cache_key = "user_orders_list_{$user_id}";
$ordersList = matrix_cache_get($cache_key);

if (!$ordersList) {
    $stmt = $pdo->prepare("
        SELECT o.id, o.status, o.total_price_paid, o.created_at, o.pass_id,
               COALESCE(p.name, ps.name) as name, 
               p.image_path, c.image_url as cat_image
        FROM orders o 
        LEFT JOIN products p ON o.product_id = p.id 
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN passes ps ON o.pass_id = ps.id
        WHERE o.user_id = ? 
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $ordersList = $stmt->fetchAll();
    matrix_cache_set($cache_key, $ordersList, 60); 
}

$is_mobile = preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
if (!$active_chat_id && count($ordersList) > 0 && !$is_mobile) {
    $active_chat_id = $ordersList[0]['id'];
}

$active_order = null;
if ($active_chat_id) {
    $stmt = $pdo->prepare("
        SELECT o.*, 
               COALESCE(p.name, ps.name) as name, 
               COALESCE(p.delivery_type, 'universal') as delivery_type, 
               p.universal_content, p.id as product_id,
               o.pass_id
        FROM orders o 
        LEFT JOIN products p ON o.product_id = p.id 
        LEFT JOIN passes ps ON o.pass_id = ps.id
        WHERE o.id = ? AND o.user_id = ?
    ");
    $stmt->execute([$active_chat_id, $user_id]);
    $active_order = $stmt->fetch();
}
?>

<!-- Dynamic Styling for Fullscreen Mobile Chat Focus -->
<style>
    body.chat-active-mobile { overflow: hidden !important; }
    body.chat-active-mobile #mobile-bottom-nav { display: none !important; }
    
    /* Smooth iOS Scroll inside chat box */
    .chat-scroll-container {
        -webkit-overflow-scrolling: touch;
        overscroll-behavior-y: contain;
    }
</style>

<!-- Main Wrapper: Fixed height to strictly prevent page scroll -->
<div class="max-w-7xl mx-auto h-[calc(100dvh-70px)] md:h-[calc(100vh-100px)] flex flex-col md:flex-row gap-0 md:gap-6 pt-0 md:py-6 px-0 md:px-4 relative w-full overflow-hidden">
    
    <!-- ========================================== -->
    <!-- LEFT SIDEBAR: Order List                   -->
    <!-- ========================================== -->
    <div id="left-sidebar" class="w-full md:w-1/3 lg:w-1/4 flex-col bg-slate-900 md:bg-slate-900/80 md:backdrop-blur-xl md:border border-slate-700/50 md:rounded-2xl shadow-xl overflow-hidden shrink-0 h-full <?php echo $active_chat_id ? 'hidden md:flex' : 'flex'; ?>">
        <div class="p-5 border-b border-slate-800 md:border-slate-700/50 bg-slate-950 md:bg-slate-800/30 shrink-0 flex justify-between items-center">
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fas fa-history text-[#00f0ff]"></i> My Orders
            </h2>
            <span class="bg-slate-800 text-xs px-2 py-1 rounded text-slate-400 font-mono"><?php echo count($ordersList); ?></span>
        </div>
        
        <div class="flex-1 overflow-y-auto custom-scrollbar p-0 md:p-3 space-y-0 md:space-y-2">
            <?php if(empty($ordersList)): ?>
                <div class="text-center py-12 text-slate-500">
                    <div class="w-16 h-16 bg-slate-800/50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-ghost text-2xl opacity-50"></i>
                    </div>
                    <p class="text-sm font-medium text-slate-400">No orders found.</p>
                    <a href="index.php" class="text-xs text-[#00f0ff] hover:underline mt-2 inline-block">Start Shopping</a>
                </div>
            <?php else: ?>
                <?php foreach($ordersList as $ord): 
                    $isActive = ($ord['id'] == $active_chat_id);
                    $isPass = !empty($ord['pass_id']);
                    $statusColor = match($ord['status']) {
                        'completed', 'active' => 'text-green-400 bg-green-500/10 border-green-500/20',
                        'pending' => 'text-yellow-400 bg-yellow-500/10 border-yellow-500/20',
                        'cancelled', 'rejected' => 'text-red-400 bg-red-500/10 border-red-500/20',
                        default => 'text-slate-400 bg-slate-500/10 border-slate-500/20'
                    };
                ?>
                <a href="index.php?module=user&page=orders&view_chat=<?php echo $ord['id']; ?>" 
                   id="order-item-<?php echo $ord['id']; ?>"
                   onclick="switchOrder(event, <?php echo $ord['id']; ?>)"
                   class="order-sidebar-item block p-4 md:rounded-xl border-b md:border transition-all duration-300 group <?php echo $isActive ? 'bg-slate-800/80 border-slate-700 md:border-[#00f0ff]/50 shadow-none md:shadow-[0_0_15px_rgba(0,240,255,0.05)]' : 'bg-transparent md:bg-slate-900/50 border-slate-800 md:hover:border-slate-600 hover:bg-slate-800/50'; ?>">
                    
                    <div class="flex justify-between items-start mb-1">
                        <div class="flex items-center gap-2">
                            <span class="order-id-span text-xs font-mono <?php echo $isActive ? 'text-[#00f0ff]' : 'text-slate-500 group-hover:text-slate-300'; ?>">#<?php echo $ord['id']; ?></span>
                            <?php if($isPass): ?>
                                <span class="text-[8px] bg-yellow-500/20 text-yellow-500 px-1.5 py-0.5 rounded border border-yellow-500/30 uppercase font-black tracking-widest"><i class="fas fa-crown"></i> Pass</span>
                            <?php endif; ?>
                        </div>
                        <span class="text-[9px] font-bold uppercase px-2 py-0.5 rounded border tracking-wider <?php echo $statusColor; ?>"><?php echo $ord['status']; ?></span>
                    </div>
                    
                    <div class="text-sm font-bold text-white truncate <?php echo $isPass ? 'text-yellow-400 group-hover:text-yellow-300' : 'group-hover:text-[#00f0ff]'; ?> transition">
                        <?php echo htmlspecialchars($ord['name']); ?>
                    </div>
                    
                    <div class="flex justify-between items-center mt-1 text-xs text-slate-500">
                        <span><?php echo date('M d', strtotime($ord['created_at'])); ?></span>
                        <span class="font-mono font-medium text-slate-300"><?php echo number_format($ord['total_price_paid']); ?> Ks</span>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- RIGHT MAIN: Immersive Chat Window          -->
    <!-- Mobile: Absolute overlay z-[9999]          -->
    <!-- Desktop: Standard flex column              -->
    <!-- ========================================== -->
    <div id="right-pane" class="w-full md:w-2/3 lg:w-3/4 flex-col bg-slate-950 md:bg-slate-900/80 md:backdrop-blur-xl md:border border-slate-700/50 md:rounded-2xl shadow-xl overflow-hidden transition-all <?php echo $active_chat_id ? 'fixed inset-0 z-[9999] h-[100dvh] flex' : 'hidden md:flex relative h-full md:h-auto z-10'; ?>">
        
        <?php if(!$active_order): ?>
            <!-- Empty State -->
            <div class="flex-1 flex flex-col items-center justify-center text-slate-500 relative">
                <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PHBhdHRlcm4gaWQ9ImdyaWQiIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCIgcGF0dGVyblVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+PHBhdGggZD0iTSAwIDEwIEwgNDAgMTAgTSAxMCAwIEwgMTAgNDAiIGZpbGw9Im5vbmUiIHN0cm9rZT0icmdiYSgyNTUsIDI1NSwgMjU1LCAwLjAyKSIgc3Ryb2tlLXdpZHRoPSIxIi8+PC9wYXR0ZXJuPjwvZGVmcz48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSJ1cmwoI2dyaWQpIi8+PC9zdmc+')] opacity-50"></div>
                <div class="relative z-10 text-center">
                    <i class="fas fa-comments text-6xl mb-6 opacity-20 text-[#00f0ff]"></i>
                    <p class="font-medium text-slate-300">Select an order</p>
                    <p class="text-sm mt-1">to open the chat window.</p>
                </div>
            </div>
        <?php else: 
            $statusColorHeader = match($active_order['status']) {
                'completed', 'active' => 'text-green-400 bg-green-500/10 border-green-500/20',
                'pending' => 'text-yellow-400 bg-yellow-500/10 border-yellow-500/20',
                'cancelled', 'rejected' => 'text-red-400 bg-red-500/10 border-red-500/20',
                default => 'text-slate-400 bg-slate-500/10 border-slate-500/20'
            };
        ?>
            
            <!-- Chat Header -->
            <div class="p-3 md:p-5 border-b border-slate-800 md:border-slate-700/50 bg-slate-900 md:bg-slate-800/80 backdrop-blur shrink-0 flex flex-col z-20 shadow-md relative pt-[max(env(safe-area-inset-top),0.75rem)]">
                
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <!-- Enhanced Mobile Back Button -->
                        <button onclick="showMobileSidebar(event)" class="md:hidden flex items-center justify-center w-10 h-10 -ml-2 text-white hover:text-[#00f0ff] bg-slate-800/50 border border-slate-700 rounded-xl transition-all active:scale-90 shadow-lg">
                            <i class="fas fa-arrow-left text-lg"></i>
                        </button>

                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-600 to-blue-800 flex items-center justify-center text-white shrink-0 border border-blue-400/30 shadow-lg">
                            <i class="fas fa-headset text-lg"></i>
                        </div>

                        <div class="min-w-0">
                            <h2 class="text-sm md:text-lg font-bold text-white truncate leading-tight flex items-center gap-2">
                                <?php echo htmlspecialchars($active_order['name']); ?>
                            </h2>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-[10px] text-slate-400 font-mono">Order #<?php echo $active_order['id']; ?></span>
                                <span class="w-1 h-1 bg-slate-600 rounded-full"></span>
                                <span class="text-[10px] text-green-400 font-bold uppercase flex items-center gap-1">
                                    <span class="inline-block w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span> Agent Online
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col items-end shrink-0">
                        <span class="text-[9px] font-black uppercase px-2 py-1 rounded border tracking-widest <?php echo $statusColorHeader; ?> shadow-sm">
                            <?php echo $active_order['status']; ?>
                        </span>
                        <span class="text-[9px] text-slate-500 mt-1 font-mono"><?php echo date('M d, H:i', strtotime($active_order['created_at'])); ?></span>
                    </div>
                </div>
            </div>

            <!-- CHAT AREA OR REJECTED STATE -->
            <?php if($active_order['status'] === 'rejected'): ?>
                <div class="flex-grow flex flex-col items-center justify-center p-8 text-center relative overflow-hidden bg-slate-900/50">
                    <div class="absolute inset-0 bg-gradient-to-t from-red-900/10 to-transparent pointer-events-none"></div>
                    <div class="w-20 h-20 bg-red-500/10 rounded-full flex items-center justify-center mb-4 border border-red-500/30 shadow-[0_0_30px_rgba(239,68,68,0.15)] relative z-10">
                        <i class="fas fa-times text-4xl text-red-500"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-2 relative z-10 tracking-tight">Order Cancelled</h3>
                    <p class="text-slate-400 text-sm max-w-sm mb-6 relative z-10 leading-relaxed">
                        Payment verification failed. The secure chat has been closed.
                    </p>
                </div>
            <?php else: ?>
                <!-- LIVE CHAT AREA (Scrollable Container) -->
                <div class="flex-grow overflow-y-auto p-4 md:p-6 bg-slate-950 md:bg-slate-900/40 chat-scroll-container relative z-0" id="chatBox">
                    <div class="flex justify-center mb-6 mt-2">
                        <div class="bg-yellow-500/10 text-yellow-500 text-[10px] font-bold px-3 py-1.5 rounded-lg border border-yellow-500/20 backdrop-blur-sm shadow-sm flex items-center gap-2 text-center max-w-sm">
                            <i class="fas fa-lock"></i> Messages are end-to-End encrypted. Admins will never ask for your passwords outside of the checkout form.
                        </div>
                    </div>

                    <!-- AUTO-DELIVERY CONTENT BOX (Rendered inside chat flow) -->
                    <?php if($active_order['delivery_type'] == 'universal' && in_array($active_order['status'], ['active', 'completed']) && !empty($active_order['universal_content'])): ?>
                        <div class="flex w-full justify-start mb-6 animate-fade-in-up">
                            <div class="max-w-[85%] md:max-w-[75%] flex flex-col items-start">
                                <div class="px-4 py-3 text-sm relative bg-slate-800 text-slate-200 border border-[#00f0ff]/50 rounded-2xl rounded-bl-sm shadow-[0_0_15px_rgba(0,240,255,0.1)]">
                                    <div class="text-[10px] font-black text-[#00f0ff] mb-2 border-b border-white/10 pb-1 uppercase tracking-wider flex items-center gap-1.5">
                                        <i class="fas fa-gift"></i> Auto Delivery System
                                    </div>
                                    <p class="mb-2 text-xs">Here are your requested credentials/details:</p>
                                    <div class="font-mono text-xs whitespace-pre-wrap select-all bg-black/40 text-green-400 p-2.5 rounded-lg border border-white/10">
                                        <?php echo htmlspecialchars($active_order['universal_content']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div id="chatMessagesContainer" class="space-y-4">
                        <div class="text-center py-4 text-slate-500 text-xs flex justify-center items-center gap-2">
                            <i class="fas fa-circle-notch fa-spin text-[#00f0ff]"></i> Loading messages...
                        </div>
                    </div>
                </div>

                <!-- CHAT INPUT (Pinned to bottom, accounts for iOS safe area) -->
                <form id="chatForm" class="p-3 bg-slate-900 border-t border-slate-800 shrink-0 relative z-20 pb-[calc(0.75rem+env(safe-area-inset-bottom))]">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="order_id" value="<?php echo $active_chat_id; ?>">
                    
                    <div class="flex items-center gap-2 max-w-4xl mx-auto">
                        <div class="relative flex-grow group" id="inputWrapper">
                            <input type="text" name="message" id="chatInput" placeholder="Type your message..." required autocomplete="off"
                                   class="w-full bg-slate-800 border border-slate-700 rounded-full py-3 pl-5 pr-14 text-sm text-white focus:border-[#00f0ff] focus:bg-slate-900 outline-none transition-all placeholder-slate-500">
                            <button type="submit" class="absolute right-1 top-1 bottom-1 bg-blue-600 hover:bg-blue-500 text-white w-10 rounded-full flex items-center justify-center shadow-lg transition transform active:scale-95">
                                <i class="fas fa-paper-plane text-sm ml-[-2px] mt-[1px]"></i>
                            </button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ===================================================================================== -->
<!-- SPA GLOBAL LOGIC (Loaded Once)                                                        -->
<!-- ===================================================================================== -->
<script>
    let currentOrderId = <?php echo $active_chat_id; ?>;
    let isUserScrolling = false;
    let lastChatHtml = '';
    let pollInterval = null;

    // Toggle body scroll lock for mobile
    function toggleMobileFullscreen(isActive) {
        if (window.innerWidth < 768) {
            if (isActive) {
                document.body.classList.add('chat-active-mobile');
            } else {
                document.body.classList.remove('chat-active-mobile');
            }
        }
    }
    
    // Initial check on load
    if(currentOrderId > 0) toggleMobileFullscreen(true);

    // Scroll Detection Setup
    document.addEventListener('scroll', function(e) {
        if(e.target && e.target.id === 'chatBox') {
            const chatBox = e.target;
            const isAtBottom = chatBox.scrollHeight - chatBox.scrollTop <= chatBox.clientHeight + 50;
            isUserScrolling = !isAtBottom;
        }
    }, true);

    function scrollToBottom() {
        const chatBox = document.getElementById('chatBox');
        if(chatBox) chatBox.scrollTop = chatBox.scrollHeight;
    }

    // Chat Polling Logic
    function fetchChat() {
        if(!currentOrderId) return;
        
        fetch(`index.php?module=user&page=orders&view_chat=${currentOrderId}&ajax=1`)
            .then(res => {
                if(res.status === 304) return null; // ETag matched
                if(!res.ok) throw new Error('Network error');
                return res.text();
            })
            .then(html => {
                if(html === null) return; 
                
                const container = document.getElementById('chatMessagesContainer');
                if(!container) return;
                
                if(html.trim() !== '' && html !== lastChatHtml) {
                    container.innerHTML = html;
                    lastChatHtml = html;
                    
                    if(!isUserScrolling) {
                        scrollToBottom();
                    }
                } else if(html.trim() === '' && container.innerHTML.includes('Loading messages')) {
                    container.innerHTML = '<div class="text-center py-6 text-slate-500 text-xs">No messages yet. Say hello!</div>';
                }
            })
            .catch(err => {});
    }

    // Start Initial Polling
    if(currentOrderId) {
        fetchChat();
        pollInterval = setInterval(fetchChat, 3000);
        
        if(window.innerWidth > 768) {
            const input = document.getElementById('chatInput');
            if(input) input.focus();
        }
    }

    // Handle AJAX Message Submission (With 3-Stage Read Receipt UI)
    document.addEventListener('submit', async function(e) {
        if(e.target && e.target.id === 'chatForm') {
            e.preventDefault();
            const form = e.target;
            const input = document.getElementById('chatInput');
            const container = document.getElementById('chatMessagesContainer');
            
            if(!input || !input.value.trim()) return;

            const msgText = input.value.trim();
            const formData = new FormData(form);
            formData.append('ajax_msg', '1');
            
            // ⚡️ STAGE 1: OPTIMISTIC UI ("Sent" - 1 Tick)
            const timeNow = new Date().toLocaleTimeString('en-US', {hour: '2-digit', minute:'2-digit', hour12: true});
            const safeHtmlText = msgText.replace(/</g, '&lt;').replace(/>/g, '&gt;');
            
            const tempId = 'temp-' + Date.now();
            const tempHtml = `
            <div class='flex w-full justify-end mb-5 animate-fade-in-up group temp-msg' id='${tempId}'>
                <div class='max-w-[85%] md:max-w-[70%] flex flex-col items-end'>
                    <div class='px-4 py-3.5 text-[13px] md:text-sm relative bg-gradient-to-br from-[#00f0ff] to-blue-600 text-slate-900 font-bold rounded-2xl rounded-br-sm shadow-[0_4px_20px_rgba(0,240,255,0.2)]'>
                        <div class='whitespace-pre-wrap break-words leading-relaxed'>${safeHtmlText}</div>
                    </div>
                    <div class='flex items-center gap-2 mt-1.5 px-1 opacity-60'>
                        <i class='fas fa-check text-[10px] text-slate-500' title='Sent'></i>
                        <span class='text-[10px] text-slate-500 font-mono font-bold tracking-tight'>${timeNow}</span>
                    </div>
                </div>
            </div>`;
            
            if(container) {
                if(container.innerHTML.includes('No messages yet')) container.innerHTML = '';
                container.insertAdjacentHTML('beforeend', tempHtml);
                scrollToBottom();
            }

            input.value = ''; 
            
            try {
                const res = await fetch('index.php?module=user&page=orders', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                
                // ⚡️ STAGE 2: "Delivered" - Server confirms save, polling upgrades to 2 Gray Ticks
                isUserScrolling = false;
                fetchChat(); 
            } catch(err) { 
                console.error('Transmission failed:', err); 
                // Mark temp message as failed
                const tempEl = document.getElementById(tempId);
                if(tempEl) {
                    const tick = tempEl.querySelector('i.fa-check');
                    if(tick) tick.className = 'fas fa-exclamation-circle text-[10px] text-red-500';
                }
            }
        }
    });

    // Handle Order Switching (PJAX/SPA style)
    async function switchOrder(e, id) {
        if (e) e.preventDefault();
        if (id === currentOrderId && window.innerWidth >= 768) return;
        
        currentOrderId = id;
        lastChatHtml = '';
        isUserScrolling = false;

        window.history.pushState({id}, '', `index.php?module=user&page=orders&view_chat=${id}`);

        document.querySelectorAll('.order-sidebar-item').forEach(el => {
            el.classList.remove('bg-slate-800/80', 'border-[#00f0ff]/50', 'shadow-[0_0_15px_rgba(0,240,255,0.05)]');
            el.classList.add('bg-transparent', 'md:bg-slate-900/50', 'border-slate-800');
            const span = el.querySelector('.order-id-span');
            if(span) { span.classList.remove('text-[#00f0ff]'); span.classList.add('text-slate-500'); }
        });
        
        const activeEl = document.getElementById('order-item-' + id);
        if(activeEl) {
            activeEl.classList.remove('bg-transparent', 'md:bg-slate-900/50', 'border-slate-800');
            activeEl.classList.add('bg-slate-800/80', 'border-slate-700', 'md:border-[#00f0ff]/50', 'shadow-none', 'md:shadow-[0_0_15px_rgba(0,240,255,0.05)]');
            const span = activeEl.querySelector('.order-id-span');
            if(span) { span.classList.add('text-[#00f0ff]'); span.classList.remove('text-slate-500'); }
        }

        const rightPane = document.getElementById('right-pane');
        const leftSidebar = document.getElementById('left-sidebar');
        
        // Show Fullscreen Mobile Loading State
        if(window.innerWidth < 768) {
            toggleMobileFullscreen(true);
            if(leftSidebar) leftSidebar.classList.add('hidden');
            rightPane.className = "fixed inset-0 z-[9999] w-full h-[100dvh] flex flex-col bg-slate-950 transition-all";
            rightPane.innerHTML = `
                <div class="p-4 border-b border-slate-800 flex items-center pt-[max(env(safe-area-inset-top),1rem)]">
                    <button onclick="showMobileSidebar()" class="text-white p-2 -ml-2 w-10 h-10 flex items-center justify-center bg-slate-800 rounded-full border border-slate-700 shadow-lg"><i class="fas fa-arrow-left"></i></button>
                    <div class="ml-4 flex flex-col">
                        <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Opening Secure Chat</span>
                        <span class="text-xs text-[#00f0ff] font-bold">Connecting to Node #${id}...</span>
                    </div>
                </div>
                <div class="flex-1 flex flex-col items-center justify-center text-slate-500">
                    <div class="relative w-16 h-16 mb-6">
                        <div class="absolute inset-0 border-4 border-[#00f0ff]/20 rounded-full"></div>
                        <div class="absolute inset-0 border-4 border-t-[#00f0ff] rounded-full animate-spin"></div>
                    </div>
                    <p class="font-bold text-xs uppercase tracking-[0.3em] animate-pulse">Initializing Matrix...</p>
                </div>
            `;
        } else {
            rightPane.innerHTML = `
                <div class="flex-1 flex flex-col items-center justify-center text-slate-500 h-full animate-pulse">
                    <i class="fas fa-circle-notch fa-spin text-4xl text-[#00f0ff] mb-4"></i>
                    <p class="font-medium tracking-widest uppercase text-xs">Opening Chat...</p>
                </div>
            `;
        }

        try {
            const res = await fetch(`index.php?module=user&page=orders&view_chat=${id}`);
            const text = await res.text();
            
            const doc = new DOMParser().parseFromString(text, 'text/html');
            const newPane = doc.getElementById('right-pane');
            
            if(newPane) {
                rightPane.innerHTML = newPane.innerHTML;
                clearInterval(pollInterval);
                fetchChat();
                pollInterval = setInterval(fetchChat, 3000);
            }
        } catch(err) {
            rightPane.innerHTML = '<div class="p-8 text-red-500 text-center flex flex-col items-center justify-center h-full"><i class="fas fa-exclamation-triangle text-4xl mb-3"></i><p>Connection lost. Please refresh.</p></div>';
        }
    }

    // Mobile Back Button Function
    function showMobileSidebar(e) {
        const btn = e?.currentTarget;
        if (btn) btn.innerHTML = '<i class="fas fa-circle-notch fa-spin text-sm"></i>';
        
        const rightPane = document.getElementById('right-pane');
        const leftSidebar = document.getElementById('left-sidebar');
        
        setTimeout(() => {
            if (rightPane) rightPane.className = "hidden md:flex relative h-full md:h-auto z-10 w-full md:w-2/3 lg:w-3/4 flex-col bg-slate-950 md:bg-slate-900/80 md:backdrop-blur-xl md:border border-slate-700/50 md:rounded-2xl shadow-xl overflow-hidden transition-all";
            if (leftSidebar) leftSidebar.classList.remove('hidden');
            if (leftSidebar) leftSidebar.classList.add('flex');

            toggleMobileFullscreen(false);
            currentOrderId = 0;
            clearInterval(pollInterval);
            
            window.history.pushState({}, '', 'index.php?module=user&page=orders');
        }, 150);
    }
    
    // ⚡️ OPTIMIZATION: Dynamic Polling Frequency
    document.addEventListener('visibilitychange', () => {
        if (currentOrderId > 0) {
            clearInterval(pollInterval);
            if (document.visibilityState === 'visible') {
                fetchChat();
                pollInterval = setInterval(fetchChat, 3000); // Fast 3s when active
            } else {
                pollInterval = setInterval(fetchChat, 15000); // Slow 15s when backgrounded
            }
        }
    });
    
    window.addEventListener('popstate', (e) => {
        if(e.state && e.state.id) {
            switchOrder({preventDefault:()=>{}}, e.state.id);
        } else {
            if(window.innerWidth < 768) showMobileSidebar();
        }
    });
</script>