<?php
// modules/user/orders.php
// PRODUCTION DEPLOYMENT v2.0 - Fully Mobile Responsive

if (!is_logged_in()) redirect('index.php?module=auth&page=login');

$user_id = $_SESSION['user_id'];
// Set active chat ID from GET, or 0 if none
$active_chat_id = isset($_GET['view_chat']) ? (int)$_GET['view_chat'] : 0;

// =====================================================================================
// 1. AJAX ENDPOINT FOR LIVE CHAT POLLING
// =====================================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] == 1 && $active_chat_id > 0) {
    // Prevent AJAX caching for real-time updates
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");

    // Verify ownership
    $stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$active_chat_id, $user_id]);
    if (!$stmt->fetch()) { http_response_code(403); exit; }

    $stmt = $pdo->prepare("SELECT * FROM order_messages WHERE order_id = ? ORDER BY created_at ASC");
    $stmt->execute([$active_chat_id]);
    $messages = $stmt->fetchAll();
    
    foreach($messages as $msg) {
        $is_user = $msg['sender_type'] === 'user';
        $align = $is_user ? 'justify-end' : 'justify-start';
        $item_align = $is_user ? 'items-end' : 'items-start';
        $bubble_bg = $is_user ? 'bg-blue-600 text-white rounded-br-none border-blue-500 shadow-[0_0_10px_rgba(37,99,235,0.2)]' : 'bg-slate-800 text-slate-200 rounded-bl-none border-slate-600 shadow-md';
        $time = date('H:i', strtotime($msg['created_at']));
        $safe_msg = htmlspecialchars($msg['message']);

        echo "<div class='flex w-full {$align} mb-4 animate-fade-in-up'>";
        echo "<div class='max-w-[85%] md:max-w-[75%] flex flex-col {$item_align}'>";
        echo "<div class='px-4 py-3 text-sm relative rounded-2xl border {$bubble_bg}'>";
        
        if ($msg['is_credential']) {
            echo "<div class='flex items-center gap-2 text-[10px] font-bold text-[#00f0ff] mb-2 border-b border-white/10 pb-1 uppercase tracking-wider'><i class='fas fa-shield-alt'></i> Secure Data</div>";
            echo "<div class='font-mono text-xs whitespace-pre-wrap select-all bg-black/40 p-2.5 rounded-lg border border-white/5 text-green-400'>{$safe_msg}</div>";
        } else {
            echo "<div class='whitespace-pre-wrap break-words leading-relaxed'>{$safe_msg}</div>";
        }
        
        echo "</div>";
        echo "<span class='text-[10px] text-slate-500 mt-1.5 px-1 font-medium'>{$time}</span>";
        echo "</div></div>";
    }
    exit;
}

// =====================================================================================
// 2. NORMAL PAGE LOAD LOGIC
// =====================================================================================

// Handle New Message Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    if (hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $msg = trim($_POST['message']);
        $oid = (int)$_POST['order_id'];
        
        // Verify Ownership & Status
        $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ? AND user_id = ?");
        $stmt->execute([$oid, $user_id]);
        $order_check = $stmt->fetch();
        
        // Only allow messaging if not rejected
        if ($order_check && $msg !== '' && $order_check['status'] !== 'rejected') {
            try {
                $pdo->exec("SET NAMES 'utf8mb4'"); // Support emojis
                $stmt = $pdo->prepare("INSERT INTO order_messages (order_id, sender_type, message) VALUES (?, 'user', ?)");
                $stmt->execute([$oid, $msg]);
            } catch (Exception $e) {}
            redirect("index.php?module=user&page=orders&view_chat=" . $oid);
        }
    }
}

// Fetch All Orders for Sidebar
$stmt = $pdo->prepare("
    SELECT o.id, o.status, o.total_price_paid, o.created_at, p.name, p.image_path, c.icon_class
    FROM orders o 
    JOIN products p ON o.product_id = p.id 
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE o.user_id = ? 
    ORDER BY o.created_at DESC
");
$stmt->execute([$user_id]);
$ordersList = $stmt->fetchAll();

// Default to latest order if none selected AND user is on desktop
// On mobile, we prefer showing the list first if no chat is explicitly selected.
$is_mobile = preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
if (!$active_chat_id && count($ordersList) > 0 && !$is_mobile) {
    $active_chat_id = $ordersList[0]['id'];
}

// Fetch Active Order Details
$active_order = null;
if ($active_chat_id) {
    $stmt = $pdo->prepare("
        SELECT o.*, p.name, p.delivery_type, p.universal_content, p.id as product_id
        FROM orders o 
        JOIN products p ON o.product_id = p.id 
        WHERE o.id = ? AND o.user_id = ?
    ");
    $stmt->execute([$active_chat_id, $user_id]);
    $active_order = $stmt->fetch();
}

// Mobile display logic classes
$sidebar_display = $active_chat_id ? 'hidden md:flex' : 'flex';
$main_display = $active_chat_id ? 'flex' : 'hidden md:flex';
?>

<div class="max-w-7xl mx-auto h-[calc(100vh-100px)] flex flex-col md:flex-row gap-6 animate-fade-in-down py-6 px-4">
    
    <!-- ========================================== -->
    <!-- LEFT SIDEBAR: Order List                   -->
    <!-- ========================================== -->
    <div class="w-full md:w-1/3 lg:w-1/4 flex-col bg-slate-900/80 backdrop-blur-xl border border-slate-700/50 rounded-2xl shadow-xl overflow-hidden shrink-0 h-full <?php echo $sidebar_display; ?>">
        <div class="p-5 border-b border-slate-700/50 bg-slate-800/30 shrink-0 flex justify-between items-center">
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fas fa-history text-[#00f0ff]"></i> My Orders
            </h2>
            <span class="bg-slate-800 text-xs px-2 py-1 rounded text-slate-400 font-mono"><?php echo count($ordersList); ?></span>
        </div>
        
        <div class="flex-1 overflow-y-auto custom-scrollbar p-3 space-y-2">
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
                    $statusColor = match($ord['status']) {
                        'completed', 'active' => 'text-green-400 bg-green-500/10 border-green-500/20',
                        'pending' => 'text-yellow-400 bg-yellow-500/10 border-yellow-500/20',
                        'cancelled', 'rejected' => 'text-red-400 bg-red-500/10 border-red-500/20',
                        default => 'text-slate-400 bg-slate-500/10 border-slate-500/20'
                    };
                ?>
                <a href="index.php?module=user&page=orders&view_chat=<?php echo $ord['id']; ?>" 
                   class="block p-4 rounded-xl border transition-all duration-300 group <?php echo $isActive ? 'bg-slate-800/80 border-[#00f0ff]/50 shadow-[0_0_15px_rgba(0,240,255,0.05)]' : 'bg-slate-900/50 border-slate-800 hover:border-slate-600 hover:bg-slate-800/50'; ?>">
                    <div class="flex justify-between items-start mb-2">
                        <span class="text-xs font-mono <?php echo $isActive ? 'text-[#00f0ff]' : 'text-slate-500 group-hover:text-slate-300'; ?>">#<?php echo $ord['id']; ?></span>
                        <span class="text-[9px] font-bold uppercase px-2 py-0.5 rounded border tracking-wider <?php echo $statusColor; ?>"><?php echo $ord['status']; ?></span>
                    </div>
                    <div class="text-sm font-bold text-white truncate group-hover:text-blue-400 transition"><?php echo htmlspecialchars($ord['name']); ?></div>
                    <div class="flex justify-between items-center mt-2 text-xs text-slate-500">
                        <span><?php echo date('M d', strtotime($ord['created_at'])); ?></span>
                        <span class="font-mono font-medium text-slate-300"><?php echo number_format($ord['total_price_paid']); ?> Ks</span>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- RIGHT MAIN: Chat & Order Details           -->
    <!-- ========================================== -->
    <div class="w-full md:w-2/3 lg:w-3/4 flex-col bg-slate-900/80 backdrop-blur-xl border border-slate-700/50 rounded-2xl shadow-xl overflow-hidden h-full relative <?php echo $main_display; ?>">
        
        <?php if(!$active_order): ?>
            <!-- Empty State for Desktop -->
            <div class="flex-1 flex flex-col items-center justify-center text-slate-500 relative">
                <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PHBhdHRlcm4gaWQ9ImdyaWQiIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCIgcGF0dGVyblVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+PHBhdGggZD0iTSAwIDEwIEwgNDAgMTAgTSAxMCAwIEwgMTAgNDAiIGZpbGw9Im5vbmUiIHN0cm9rZT0icmdiYSgyNTUsIDI1NSwgMjU1LCAwLjAyKSIgc3Ryb2tlLXdpZHRoPSIxIi8+PC9wYXR0ZXJuPjwvZGVmcz48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSJ1cmwoI2dyaWQpIi8+PC9zdmc+')] opacity-50"></div>
                <div class="relative z-10 text-center">
                    <i class="fas fa-comments text-6xl mb-6 opacity-20 text-[#00f0ff]"></i>
                    <p class="font-medium text-slate-300">Select an order from the list</p>
                    <p class="text-sm mt-1">to view details and communicate with support.</p>
                </div>
            </div>
        <?php else: ?>
            
            <!-- Order Header -->
            <div class="p-5 border-b border-slate-700/50 bg-slate-800/80 backdrop-blur shrink-0 flex flex-col z-20 shadow-sm relative">
                <!-- Mobile Back Button -->
                <a href="index.php?module=user&page=orders" class="md:hidden text-slate-400 hover:text-white text-xs mb-3 flex items-center gap-2 w-max bg-slate-900 px-3 py-1.5 rounded-lg border border-slate-700">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>

                <div class="flex justify-between items-start md:items-center">
                    <div class="min-w-0 pr-4">
                        <h2 class="text-lg md:text-xl font-bold text-white truncate leading-tight"><?php echo htmlspecialchars($active_order['name']); ?></h2>
                        <div class="flex items-center gap-3 mt-1.5">
                            <span class="text-xs text-[#00f0ff] font-mono bg-[#00f0ff]/10 px-2 py-0.5 rounded border border-[#00f0ff]/20">#<?php echo $active_order['id']; ?></span>
                            <span class="text-xs text-slate-400"><i class="far fa-clock"></i> <?php echo date('M d, Y', strtotime($active_order['created_at'])); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Progress Tracker -->
                <div class="mt-6 pt-4 border-t border-slate-700/50 hidden md:block">
                    <div class="flex items-center justify-between text-[10px] font-bold uppercase tracking-widest mb-2 relative">
                        <?php 
                            $is_active = in_array($active_order['status'], ['active', 'completed']);
                            $is_rejected = ($active_order['status'] === 'rejected');
                        ?>
                        <span class="text-blue-400 z-10 bg-slate-800 pr-2">Payment Received</span>
                        <span class="<?php echo $is_active ? 'text-blue-400' : ($is_rejected ? 'text-red-400' : 'text-yellow-400 animate-pulse'); ?> z-10 bg-slate-800 px-2">Processing</span>
                        <span class="<?php echo $is_active ? 'text-green-400' : 'text-slate-600'; ?> z-10 bg-slate-800 pl-2">Delivered</span>
                        
                        <!-- Background Line -->
                        <div class="absolute left-0 right-0 top-1/2 h-0.5 bg-slate-700 -z-0 transform -translate-y-1/2">
                            <!-- Fill Line -->
                            <div class="h-full <?php echo $is_active ? 'bg-green-500 w-full' : ($is_rejected ? 'bg-red-500 w-1/2' : 'bg-blue-500 w-1/2'); ?> transition-all duration-1000 shadow-[0_0_10px_currentColor]"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submitted Form Data Review -->
            <?php if(!empty($active_order['form_data'])): ?>
                <div class="bg-slate-900/50 border-b border-slate-700/50 p-4 shrink-0 z-10">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Your Submitted Details</p>
                    <div class="flex flex-wrap gap-2">
                        <?php 
                        $formData = json_decode($active_order['form_data'], true);
                        if(is_array($formData)) {
                            foreach($formData as $key => $val): 
                        ?>
                            <div class="bg-slate-800 border border-slate-600 rounded px-2.5 py-1 text-xs flex items-center shadow-sm">
                                <span class="text-slate-400 mr-2"><?php echo htmlspecialchars($key); ?>:</span>
                                <span class="text-[#00f0ff] font-mono font-medium"><?php echo htmlspecialchars($val); ?></span>
                            </div>
                        <?php 
                            endforeach; 
                        } 
                        ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- AUTO-DELIVERY CONTENT BOX (The Goods) -->
            <?php if($active_order['delivery_type'] == 'universal' && in_array($active_order['status'], ['active', 'completed'])): ?>
                <div class="bg-blue-900/10 border-b border-[#00f0ff]/30 p-5 shrink-0 relative overflow-hidden z-10">
                    <!-- Tech Pattern Background -->
                    <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PHBhdHRlcm4gaWQ9ImdyaWQiIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCIgcGF0dGVyblVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+PHBhdGggZD0iTSAwIDEwIEwgNDAgMTAgTSAxMCAwIEwgMTAgNDAiIGZpbGw9Im5vbmUiIHN0cm9rZT0icmdiYSgwLCAyNDAsIDI1NSwgMC4wNSkiIHN0cm9rZS13aWR0aD0iMSIvPjwvcGF0dGVybj48L2RlZnM+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0idXJsKCNncmlkKSIvPjwvc3ZnPg==')] opacity-50"></div>
                    
                    <div class="relative z-10">
                        <h4 class="text-[10px] font-bold text-[#00f0ff] uppercase tracking-widest mb-2 flex items-center gap-2">
                            <i class="fas fa-gift animate-bounce"></i> Secure Delivery Content
                        </h4>
                        <div class="bg-slate-900/80 rounded-xl p-4 border border-[#00f0ff]/40 relative group transition-colors shadow-[0_0_15px_rgba(0,240,255,0.05)]">
                            <code class="text-sm text-green-300 font-mono break-all whitespace-pre-wrap select-all block pr-8"><?php echo htmlspecialchars($active_order['universal_content']); ?></code>
                            <button onclick="navigator.clipboard.writeText(`<?php echo addslashes($active_order['universal_content']); ?>`); this.innerHTML='<i class=\'fas fa-check\'></i>'; setTimeout(()=>this.innerHTML='<i class=\'fas fa-copy\'></i>', 2000);" 
                                    class="absolute top-3 right-3 w-8 h-8 rounded-lg bg-slate-800 text-slate-400 hover:text-white hover:bg-[#00f0ff] hover:border-[#00f0ff] border border-slate-600 transition shadow-lg flex items-center justify-center" title="Copy to clipboard">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ========================================== -->
            <!-- CHAT AREA OR REJECTED STATE                -->
            <!-- ========================================== -->
            <?php if($active_order['status'] === 'rejected'): ?>
                <!-- REJECTED STATE UI -->
                <div class="flex-grow flex flex-col items-center justify-center p-8 text-center relative overflow-hidden bg-slate-900/50">
                    <div class="absolute inset-0 bg-gradient-to-t from-red-900/10 to-transparent pointer-events-none"></div>
                    <div class="w-24 h-24 bg-red-500/10 rounded-full flex items-center justify-center mb-6 border border-red-500/30 shadow-[0_0_30px_rgba(239,68,68,0.15)] relative z-10">
                        <i class="fas fa-times text-5xl text-red-500"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-2 relative z-10 tracking-tight">Order Rejected</h3>
                    <p class="text-slate-400 text-sm max-w-sm mb-8 relative z-10 leading-relaxed">
                        We could not verify your payment or the submitted details were incorrect. The secure channel has been closed.
                    </p>
                    <a href="index.php?module=shop&page=checkout&id=<?php echo $active_order['product_id']; ?>" class="bg-gradient-to-r from-red-600 to-red-500 hover:from-red-500 hover:to-red-400 text-white px-8 py-3.5 rounded-xl font-bold shadow-[0_0_20px_rgba(239,68,68,0.3)] transition transform hover:-translate-y-1 relative z-10 flex items-center gap-2">
                        <i class="fas fa-redo"></i> Try Buying Again
                    </a>
                </div>
            <?php else: ?>
                <!-- LIVE CHAT AREA -->
                <div class="flex-grow overflow-y-auto p-4 md:p-6 bg-slate-900/40 scroll-smooth custom-scrollbar relative z-0" id="chatBox">
                    <div class="flex justify-center mb-6 mt-2">
                        <div class="bg-slate-800/80 text-slate-400 text-[10px] font-bold uppercase tracking-wider px-3 py-1 rounded-full border border-slate-700/50 backdrop-blur-sm shadow-sm flex items-center gap-2">
                            <i class="fas fa-lock text-[#00f0ff]"></i> Secure Channel Established
                        </div>
                    </div>
                    
                    <!-- Messages Container (Populated by AJAX) -->
                    <div id="chatMessagesContainer" class="space-y-4">
                        <!-- Fallback loading state -->
                        <div class="text-center py-4 text-slate-500 text-xs flex justify-center items-center gap-2">
                            <i class="fas fa-circle-notch fa-spin text-[#00f0ff]"></i> Syncing messages...
                        </div>
                    </div>
                </div>

                <!-- CHAT INPUT -->
                <form method="POST" class="p-3 md:p-4 border-t border-slate-700/80 bg-slate-800/95 backdrop-blur shrink-0 relative z-20">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="order_id" value="<?php echo $active_chat_id; ?>">
                    
                    <div class="relative flex items-center gap-2">
                        <div class="relative flex-grow group">
                            <input type="text" name="message" id="chatInput" placeholder="Type a message to support..." required autocomplete="off"
                                   class="w-full bg-slate-900 border border-slate-600 rounded-full py-3.5 pl-5 pr-14 text-sm text-white focus:border-[#00f0ff] focus:ring-1 focus:ring-[#00f0ff] focus:outline-none transition shadow-inner placeholder-slate-500">
                            <button type="submit" class="absolute right-1.5 top-1.5 bg-gradient-to-r from-blue-600 to-[#00f0ff] hover:from-blue-500 hover:to-[#00f0ff] text-slate-900 w-10 h-10 rounded-full flex items-center justify-center shadow-[0_0_10px_rgba(0,240,255,0.3)] transition transform active:scale-95 group-focus-within:shadow-[0_0_15px_rgba(0,240,255,0.5)]">
                                <i class="fas fa-paper-plane text-sm"></i>
                            </button>
                        </div>
                    </div>
                </form>

                <!-- LIVE AJAX SCRIPT -->
                <script>
                    const chatBox = document.getElementById('chatBox');
                    const msgContainer = document.getElementById('chatMessagesContainer');
                    const orderId = <?php echo $active_chat_id; ?>;
                    let isUserScrolling = false;

                    // Detect if user is scrolling up to prevent auto-scroll jumps
                    if(chatBox) {
                        chatBox.addEventListener('scroll', () => {
                            // Check if within 50px of bottom
                            const isAtBottom = chatBox.scrollHeight - chatBox.scrollTop <= chatBox.clientHeight + 50;
                            isUserScrolling = !isAtBottom;
                        });
                    }

                    function fetchChat() {
                        if(!orderId) return;
                        
                        // Added timestamp to prevent browser caching the request
                        fetch(`index.php?module=user&page=orders&view_chat=${orderId}&ajax=1&_=${Date.now()}`)
                            .then(response => {
                                if(!response.ok) throw new Error('Network response error');
                                return response.text();
                            })
                            .then(html => {
                                // Only update DOM if there's content to avoid flicker
                                if(html.trim() !== '') {
                                    msgContainer.innerHTML = html;
                                    if (!isUserScrolling) {
                                        chatBox.scrollTop = chatBox.scrollHeight;
                                    }
                                } else if (msgContainer.innerHTML.includes('Syncing')) {
                                     msgContainer.innerHTML = '<div class="text-center py-4 text-slate-500 text-xs">Send a message to start the conversation.</div>';
                                }
                            })
                            .catch(err => console.error('Chat Sync Error:', err));
                    }

                    // Initial fetch & set interval (3s polling)
                    fetchChat();
                    const pollInterval = setInterval(fetchChat, 3000); 

                    // Focus input on load (Desktop only)
                    if(window.innerWidth > 768) {
                        const input = document.getElementById('chatInput');
                        if(input) input.focus();
                    }
                </script>
            <?php endif; ?>
            
        <?php endif; ?>
    </div>
</div>