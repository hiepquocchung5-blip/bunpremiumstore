<?php
// modules/user/orders.php

// 1. Handle New Chat Message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'], $_POST['order_id'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) die("Invalid Request");
    
    $msg = trim($_POST['message']);
    $order_id = (int)$_POST['order_id'];
    
    // Security: Verify Order belongs to User
    $stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    
    if ($stmt->fetch() && !empty($msg)) {
        $stmt = $pdo->prepare("INSERT INTO order_messages (order_id, sender_type, message) VALUES (?, 'user', ?)");
        $stmt->execute([$order_id, $msg]);
        // Refresh to show message
        redirect("index.php?module=user&page=orders&view_chat=$order_id");
    }
}

// 2. Fetch Orders List
$stmt = $pdo->prepare("
    SELECT o.*, p.name as product_name, p.delivery_type, p.universal_content, p.id as product_id
    FROM orders o 
    JOIN products p ON o.product_id = p.id 
    WHERE o.user_id = ? 
    ORDER BY o.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll();

// 3. Chat Logic (If an order is selected)
$active_chat_id = isset($_GET['view_chat']) ? (int)$_GET['view_chat'] : null;
$chat_messages = [];
$active_order = null;

if ($active_chat_id) {
    // Verify ownership
    foreach ($orders as $o) {
        if ($o['id'] == $active_chat_id) {
            $active_order = $o;
            break;
        }
    }

    if ($active_order) {
        // Fetch Messages
        $stmt = $pdo->prepare("SELECT * FROM order_messages WHERE order_id = ? ORDER BY created_at ASC");
        $stmt->execute([$active_chat_id]);
        $chat_messages = $stmt->fetchAll();
    } else {
        $active_chat_id = null; // Access denied or not found
    }
}
?>

<div class="flex flex-col lg:flex-row gap-6 h-[calc(100vh-140px)] min-h-[600px]">
    
    <!-- LEFT COLUMN: Order List -->
    <!-- On mobile, hide this column if a chat is active -->
    <div class="<?php echo $active_chat_id ? 'hidden lg:block lg:w-1/3' : 'w-full'; ?> flex flex-col glass rounded-2xl border border-gray-700/50 overflow-hidden shadow-2xl">
        
        <div class="p-4 border-b border-gray-700/50 bg-gray-800/30 backdrop-blur-md sticky top-0 z-10">
            <h2 class="text-xl font-bold text-white flex items-center gap-2">
                <i class="fas fa-history text-blue-500"></i> My Orders
            </h2>
        </div>
        
        <div class="flex-1 overflow-y-auto custom-scrollbar p-3 space-y-3">
            <?php if(empty($orders)): ?>
                <div class="text-center py-10 text-gray-500">
                    <i class="fas fa-shopping-bag text-4xl mb-3 opacity-50"></i>
                    <p>No orders yet.</p>
                    <a href="index.php" class="text-blue-400 text-sm hover:underline mt-2 inline-block">Start Shopping</a>
                </div>
            <?php else: ?>
                <?php foreach($orders as $order): ?>
                    <?php $isActive = ($active_chat_id == $order['id']); ?>
                    <a href="index.php?module=user&page=orders&view_chat=<?php echo $order['id']; ?>" 
                       class="block p-4 rounded-xl transition-all duration-200 border relative group
                       <?php echo $isActive ? 'bg-blue-600/10 border-blue-500/50 shadow-[0_0_15px_rgba(59,130,246,0.15)]' : 'bg-gray-800/40 border-transparent hover:bg-gray-800 hover:border-gray-600'; ?>">
                        
                        <div class="flex justify-between items-start mb-2">
                            <h3 class="font-bold text-white truncate pr-2 text-sm"><?php echo htmlspecialchars($order['product_name']); ?></h3>
                            <?php if($order['status'] == 'active'): ?>
                                <span class="text-[10px] font-bold bg-green-500/10 text-green-400 px-2 py-0.5 rounded border border-green-500/20">Active</span>
                            <?php elseif($order['status'] == 'pending'): ?>
                                <span class="text-[10px] font-bold bg-yellow-500/10 text-yellow-400 px-2 py-0.5 rounded border border-yellow-500/20">Pending</span>
                            <?php else: ?>
                                <span class="text-[10px] font-bold bg-red-500/10 text-red-400 px-2 py-0.5 rounded border border-red-500/20">Rejected</span>
                            <?php endif; ?>
                        </div>

                        <div class="flex justify-between items-end text-xs text-gray-400">
                            <div>
                                <p class="font-mono text-gray-500">#<?php echo $order['id']; ?></p>
                                <p class="mt-0.5"><?php echo date('M d', strtotime($order['created_at'])); ?></p>
                            </div>
                            <div class="text-right">
                                <span class="font-bold text-white"><?php echo format_price($order['total_price_paid']); ?></span>
                                <div class="text-[10px] text-gray-500 mt-0.5">
                                    <?php echo ucfirst($order['delivery_type']); ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Hover Glow Effect -->
                        <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/5 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000 pointer-events-none"></div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- RIGHT COLUMN: Chat & Delivery -->
    <!-- On mobile, show this only if a chat is active -->
    <?php if($active_chat_id && $active_order): ?>
    <div class="w-full lg:w-2/3 flex flex-col glass rounded-2xl border border-gray-700/50 shadow-2xl overflow-hidden relative">
        
        <!-- Chat Header -->
        <div class="p-3 md:p-4 border-b border-gray-700/50 bg-gray-900/50 backdrop-blur-md flex items-center gap-3 shrink-0 z-20">
            <!-- Mobile Back Button -->
            <a href="index.php?module=user&page=orders" class="lg:hidden w-8 h-8 rounded-full bg-gray-800 flex items-center justify-center text-gray-400 hover:text-white transition">
                <i class="fas fa-arrow-left"></i>
            </a>
            
            <div class="flex-1 min-w-0">
                <h3 class="font-bold text-white text-sm md:text-base flex items-center gap-2 truncate">
                    Order #<?php echo $active_order['id']; ?>
                    <?php if($active_order['status'] == 'active'): ?>
                        <i class="fas fa-check-circle text-green-400 text-xs" title="Order Completed"></i>
                    <?php endif; ?>
                </h3>
                <p class="text-xs text-gray-400 truncate"><?php echo htmlspecialchars($active_order['product_name']); ?></p>
            </div>
            
            <a href="index.php?module=shop&page=checkout&id=<?php echo $active_order['product_id']; ?>" class="hidden md:flex bg-blue-600/20 text-blue-400 hover:bg-blue-600 hover:text-white text-xs px-3 py-1.5 rounded-lg transition border border-blue-500/30 items-center gap-2">
                <i class="fas fa-redo"></i> Buy Again
            </a>
        </div>

        <!-- AUTO-DELIVERY BOX (Universal Products - Instant) -->
        <?php if($active_order['delivery_type'] == 'universal' && $active_order['status'] == 'active'): ?>
            <div class="bg-blue-900/10 border-b border-blue-500/20 p-4 shrink-0 relative overflow-hidden">
                <div class="absolute inset-0 bg-blue-500/5 pattern-grid-lg"></div>
                <div class="relative z-10">
                    <h4 class="text-xs font-bold text-blue-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                        <i class="fas fa-gift animate-bounce"></i> Your Content
                    </h4>
                    <div class="bg-gray-900/80 rounded-lg p-3 border border-gray-700 relative group transition-colors hover:border-blue-500/50">
                        <code class="text-sm text-gray-200 font-mono break-all whitespace-pre-wrap"><?php echo htmlspecialchars($active_order['universal_content']); ?></code>
                        <button onclick="navigator.clipboard.writeText(this.previousElementSibling.innerText); alert('Copied!');" 
                                class="absolute top-2 right-2 p-1.5 rounded bg-gray-800 text-gray-400 hover:text-white hover:bg-blue-600 transition shadow-lg opacity-0 group-hover:opacity-100" title="Copy Content">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Messages Area -->
        <div class="flex-grow overflow-y-auto p-4 space-y-3 bg-black/20 scroll-smooth custom-scrollbar relative" id="chatBox">
            
            <!-- Welcome System Message -->
            <div class="flex justify-center my-4">
                <div class="bg-gray-800/80 text-gray-400 text-[10px] px-3 py-1 rounded-full border border-gray-700 backdrop-blur-sm">
                    <?php echo date('M d, Y • H:i', strtotime($active_order['created_at'])); ?> • Order Started
                </div>
            </div>

            <?php foreach($chat_messages as $msg): ?>
                <div class="flex w-full <?php echo $msg['sender_type'] == 'user' ? 'justify-end' : 'justify-start'; ?> animate-fade-in-up">
                    <div class="max-w-[85%] md:max-w-[70%] flex flex-col <?php echo $msg['sender_type'] == 'user' ? 'items-end' : 'items-start'; ?>">
                        
                        <div class="px-4 py-2.5 text-sm shadow-sm relative <?php echo $msg['sender_type'] == 'user' ? 'chat-bubble-user' : 'chat-bubble-admin'; ?>">
                            
                            <?php if($msg['is_credential']): ?>
                                <!-- Credential Style -->
                                <div class="flex items-center gap-2 text-xs font-bold text-green-300 mb-2 border-b border-white/10 pb-1">
                                    <i class="fas fa-key"></i> SECURE DATA
                                </div>
                                <div class="font-mono text-xs whitespace-pre-wrap select-all bg-black/20 p-2 rounded border border-white/5"><?php echo htmlspecialchars($msg['message']); ?></div>
                                <button onclick="navigator.clipboard.writeText(`<?php echo addslashes($msg['message']); ?>`);" class="text-[10px] text-green-400/80 mt-1 hover:text-green-300 flex items-center gap-1 cursor-pointer">
                                    <i class="far fa-copy"></i> Click to Copy
                                </button>
                            <?php else: ?>
                                <!-- Normal Text -->
                                <div class="whitespace-pre-wrap break-words"><?php echo htmlspecialchars($msg['message']); ?></div>
                            <?php endif; ?>
                            
                        </div>
                        
                        <span class="text-[10px] text-gray-500 mt-1 px-1 opacity-70">
                            <?php echo date('H:i', strtotime($msg['created_at'])); ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Input Area -->
        <form method="POST" class="p-3 md:p-4 border-t border-gray-700 bg-gray-900/80 backdrop-blur shrink-0 relative z-20">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="order_id" value="<?php echo $active_chat_id; ?>">
            
            <div class="relative flex items-center gap-2">
                <div class="relative flex-grow">
                    <input type="text" name="message" placeholder="Type a message to admin..." required autocomplete="off"
                           class="w-full bg-gray-800 border border-gray-600 rounded-full py-3 pl-5 pr-12 text-sm text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none transition shadow-inner">
                    
                    <button type="submit" class="absolute right-1.5 top-1.5 bg-blue-600 hover:bg-blue-500 text-white w-9 h-9 rounded-full flex items-center justify-center shadow-lg transition transform active:scale-95">
                        <i class="fas fa-paper-plane text-xs"></i>
                    </button>
                </div>
                
                <!-- Optional: File Upload Button (UI Only for now) -->
                <!-- <button type="button" class="w-10 h-10 rounded-full bg-gray-800 border border-gray-600 text-gray-400 hover:text-white hover:border-gray-500 transition flex items-center justify-center" title="Attach Image">
                    <i class="fas fa-paperclip"></i>
                </button> -->
            </div>
        </form>
    </div>
    
    <!-- Auto-Scroll Script -->
    <script>
        const chatBox = document.getElementById('chatBox');
        if(chatBox) {
            chatBox.scrollTop = chatBox.scrollHeight;
            // Smooth scroll adjustment for mobile keyboard
            window.addEventListener('resize', () => chatBox.scrollTop = chatBox.scrollHeight);
        }
    </script>

    <?php else: ?>
        <!-- Desktop Placeholder (Empty State) -->
        <div class="hidden lg:flex w-2/3 glass rounded-2xl border border-gray-700/50 items-center justify-center flex-col text-gray-500 p-10 text-center">
            <div class="w-24 h-24 bg-gray-800 rounded-full flex items-center justify-center mb-6 shadow-inner border border-gray-700">
                <i class="far fa-comments text-5xl opacity-40 text-blue-400"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-300 mb-2">Order Details</h3>
            <p class="max-w-xs mx-auto text-sm">Select an order from the list to view your product details, secret keys, or chat with support.</p>
        </div>
    <?php endif; ?>

</div>