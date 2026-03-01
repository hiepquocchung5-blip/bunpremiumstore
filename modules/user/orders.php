<?php
// modules/user/orders.php

if (!is_logged_in()) redirect('index.php?module=auth&page=login');

$user_id = $_SESSION['user_id'];
$active_chat_id = isset($_GET['view_chat']) ? (int)$_GET['view_chat'] : 0;

// =====================================================================================
// 1. AJAX ENDPOINT FOR LIVE CHAT POLLING
// =====================================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] == 1 && $active_chat_id > 0) {
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
        $bubble_bg = $is_user ? 'bg-blue-600 text-white rounded-br-none border-blue-500' : 'bg-slate-700 text-slate-200 rounded-bl-none border-slate-600';
        $time = date('H:i', strtotime($msg['created_at']));
        $safe_msg = htmlspecialchars($msg['message']);

        echo "<div class='flex w-full {$align} mb-4'>";
        echo "<div class='max-w-[85%] md:max-w-[70%] flex flex-col {$item_align}'>";
        echo "<div class='px-4 py-2.5 text-sm shadow-sm relative rounded-2xl border {$bubble_bg}'>";
        
        if ($msg['is_credential']) {
            echo "<div class='flex items-center gap-2 text-xs font-bold text-[#00f0ff] mb-2 border-b border-white/20 pb-1'><i class='fas fa-shield-alt'></i> SECURE DATA</div>";
            echo "<div class='font-mono text-xs whitespace-pre-wrap select-all bg-black/40 p-2 rounded border border-white/10 text-green-300'>{$safe_msg}</div>";
        } else {
            echo "<div class='whitespace-pre-wrap break-words leading-relaxed'>{$safe_msg}</div>";
        }
        
        echo "</div>";
        echo "<span class='text-[10px] text-slate-500 mt-1 px-1'>{$time}</span>";
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
        
        if ($order_check && $msg !== '' && $order_check['status'] !== 'rejected') {
            $stmt = $pdo->prepare("INSERT INTO order_messages (order_id, sender_type, message) VALUES (?, 'user', ?)");
            $stmt->execute([$oid, $msg]);
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

// Default to latest order if none selected
if (!$active_chat_id && count($ordersList) > 0) {
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
?>

<div class="max-w-7xl mx-auto h-[calc(100vh-100px)] flex flex-col md:flex-row gap-6 animate-fade-in-down py-6 px-4">
    
    <!-- LEFT SIDEBAR: Order List -->
    <div class="w-full md:w-1/3 lg:w-1/4 flex flex-col bg-slate-900/80 backdrop-blur-xl border border-[#00f0ff]/20 rounded-2xl shadow-[0_0_15px_rgba(0,240,255,0.05)] overflow-hidden shrink-0 h-[300px] md:h-full">
        <div class="p-5 border-b border-slate-700/50 bg-slate-800/50 shrink-0">
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fas fa-box-open text-[#00f0ff]"></i> My Orders
            </h2>
        </div>
        
        <div class="flex-1 overflow-y-auto custom-scrollbar p-3 space-y-2">
            <?php if(empty($ordersList)): ?>
                <div class="text-center py-10 text-slate-500">
                    <i class="fas fa-ghost text-3xl mb-2 opacity-30"></i>
                    <p class="text-sm">No orders found.</p>
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
                   class="block p-3 rounded-xl border transition-all duration-200 <?php echo $isActive ? 'bg-slate-800 border-[#00f0ff]/50 shadow-[0_0_10px_rgba(0,240,255,0.1)]' : 'bg-slate-900/50 border-slate-700 hover:border-slate-500 hover:bg-slate-800'; ?>">
                    <div class="flex justify-between items-start mb-1">
                        <span class="text-xs font-mono text-slate-400">#<?php echo $ord['id']; ?></span>
                        <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded border <?php echo $statusColor; ?>"><?php echo $ord['status']; ?></span>
                    </div>
                    <div class="text-sm font-bold text-white truncate"><?php echo htmlspecialchars($ord['name']); ?></div>
                    <div class="text-xs text-slate-500 mt-1"><?php echo date('M d', strtotime($ord['created_at'])); ?> • <?php echo number_format($ord['total_price_paid']); ?> Ks</div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- RIGHT MAIN: Chat & Order Details -->
    <div class="w-full md:w-2/3 lg:w-3/4 flex flex-col bg-slate-900/80 backdrop-blur-xl border border-slate-700 rounded-2xl shadow-xl overflow-hidden h-[600px] md:h-full relative">
        
        <?php if(!$active_order): ?>
            <div class="flex-1 flex flex-col items-center justify-center text-slate-500">
                <i class="fas fa-comments text-5xl mb-4 opacity-20"></i>
                <p>Select an order to view details and chat.</p>
            </div>
        <?php else: ?>
            
            <!-- Header -->
            <div class="p-5 border-b border-slate-700 bg-slate-800/80 backdrop-blur shrink-0 flex justify-between items-center z-20">
                <div>
                    <h2 class="text-lg font-bold text-white truncate"><?php echo htmlspecialchars($active_order['name']); ?></h2>
                    <p class="text-xs text-[#00f0ff] font-mono">Order #<?php echo $active_order['id']; ?></p>
                </div>
                <div class="text-right hidden sm:block">
                    <p class="text-sm font-bold text-white"><?php echo number_format($active_order['total_price_paid']); ?> Ks</p>
                    <p class="text-xs text-slate-400 uppercase"><?php echo $active_order['delivery_type']; ?> Delivery</p>
                </div>
            </div>

            <!-- Submitted Form Data Review -->
            <?php if(!empty($active_order['form_data'])): ?>
                <div class="bg-slate-800/50 border-b border-slate-700 p-4 shrink-0 z-10">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Your Submitted Details</p>
                    <div class="flex flex-wrap gap-2">
                        <?php 
                        $formData = json_decode($active_order['form_data'], true);
                        if(is_array($formData)) {
                            foreach($formData as $key => $val): 
                        ?>
                            <div class="bg-slate-900 border border-slate-700 rounded px-2 py-1 text-xs">
                                <span class="text-slate-500 mr-1"><?php echo htmlspecialchars($key); ?>:</span>
                                <span class="text-[#00f0ff] font-mono"><?php echo htmlspecialchars($val); ?></span>
                            </div>
                        <?php 
                            endforeach; 
                        } 
                        ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- AUTO-DELIVERY CONTENT BOX -->
            <?php if($active_order['delivery_type'] == 'universal' && in_array($active_order['status'], ['active', 'completed'])): ?>
                <div class="bg-blue-900/20 border-b border-blue-500/30 p-4 shrink-0 relative overflow-hidden z-10">
                    <div class="absolute inset-0 bg-[#00f0ff]/5 pattern-grid-lg"></div>
                    <div class="relative z-10">
                        <h4 class="text-xs font-bold text-[#00f0ff] uppercase tracking-widest mb-2 flex items-center gap-2">
                            <i class="fas fa-gift animate-bounce"></i> Your Digital Content
                        </h4>
                        <div class="bg-slate-900/80 rounded-lg p-3 border border-slate-700 relative group transition-colors hover:border-[#00f0ff]/50">
                            <code class="text-sm text-slate-200 font-mono break-all whitespace-pre-wrap select-all"><?php echo htmlspecialchars($active_order['universal_content']); ?></code>
                            <button onclick="navigator.clipboard.writeText(`<?php echo addslashes($active_order['universal_content']); ?>`); alert('Copied to clipboard!');" 
                                    class="absolute top-2 right-2 p-1.5 rounded bg-slate-800 text-slate-400 hover:text-white hover:bg-[#00f0ff] transition shadow-lg opacity-0 group-hover:opacity-100" title="Copy Content">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- CHAT AREA OR REJECTED STATE -->
            <?php if($active_order['status'] === 'rejected'): ?>
                <!-- REJECTED STATE UI (Hides Chat) -->
                <div class="flex-grow flex flex-col items-center justify-center p-8 text-center bg-red-900/10 relative overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-t from-red-900/20 to-transparent pointer-events-none"></div>
                    <div class="w-20 h-20 bg-red-500/10 rounded-full flex items-center justify-center mb-6 border border-red-500/30 shadow-[0_0_30px_rgba(239,68,68,0.2)] relative z-10">
                        <i class="fas fa-times text-4xl text-red-500"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-2 relative z-10">Order Rejected</h3>
                    <p class="text-slate-400 text-sm max-w-sm mb-8 relative z-10 leading-relaxed">
                        We could not verify your payment or your details were incorrect. The chat channel has been closed.
                    </p>
                    <a href="index.php?module=shop&page=checkout&id=<?php echo $active_order['product_id']; ?>" class="bg-red-600 hover:bg-red-500 text-white px-8 py-3 rounded-xl font-bold shadow-lg shadow-red-900/30 transition transform hover:-translate-y-1 relative z-10 flex items-center gap-2">
                        <i class="fas fa-redo"></i> Try Buying Again
                    </a>
                </div>
            <?php else: ?>
                <!-- Live Chat Area -->
                <div class="flex-grow overflow-y-auto p-4 md:p-6 bg-black/20 scroll-smooth custom-scrollbar relative z-0" id="chatBox">
                    <div class="flex justify-center my-4">
                        <div class="bg-slate-800/80 text-slate-400 text-[10px] px-3 py-1 rounded-full border border-slate-700 backdrop-blur-sm">
                            <?php echo date('M d, Y • H:i', strtotime($active_order['created_at'])); ?> • Order Started
                        </div>
                    </div>
                    
                    <div id="chatMessagesContainer">
                        <!-- Messages will be loaded here via AJAX -->
                    </div>
                </div>

                <!-- Chat Input -->
                <form method="POST" class="p-3 md:p-4 border-t border-slate-700 bg-slate-900/90 backdrop-blur shrink-0 relative z-20">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="order_id" value="<?php echo $active_chat_id; ?>">
                    
                    <div class="relative flex items-center gap-2">
                        <div class="relative flex-grow">
                            <input type="text" name="message" id="chatInput" placeholder="Type a message to support..." required autocomplete="off"
                                   class="w-full bg-slate-800 border border-slate-600 rounded-full py-3 pl-5 pr-12 text-sm text-white focus:border-[#00f0ff] focus:ring-1 focus:ring-[#00f0ff] focus:outline-none transition shadow-inner">
                            <button type="submit" class="absolute right-1.5 top-1.5 bg-gradient-to-r from-blue-600 to-[#00f0ff] hover:from-blue-500 hover:to-[#00f0ff] text-slate-900 w-9 h-9 rounded-full flex items-center justify-center shadow-[0_0_10px_rgba(0,240,255,0.3)] transition transform active:scale-95">
                                <i class="fas fa-paper-plane text-xs"></i>
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
                    chatBox.addEventListener('scroll', () => {
                        const isAtBottom = chatBox.scrollHeight - chatBox.scrollTop <= chatBox.clientHeight + 50;
                        isUserScrolling = !isAtBottom;
                    });

                    function fetchChat() {
                        fetch(`index.php?module=user&page=orders&view_chat=${orderId}&ajax=1`)
                            .then(response => response.text())
                            .then(html => {
                                msgContainer.innerHTML = html;
                                if (!isUserScrolling) {
                                    chatBox.scrollTop = chatBox.scrollHeight;
                                }
                            })
                            .catch(err => console.error('Chat Sync Error:', err));
                    }

                    // Initial fetch & set interval
                    fetchChat();
                    setInterval(fetchChat, 3000); // Poll every 3 seconds

                    // Focus input on load
                    document.getElementById('chatInput').focus();
                </script>
            <?php endif; ?>
            
        <?php endif; ?>
    </div>
</div>