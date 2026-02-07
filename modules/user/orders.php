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
    SELECT o.*, p.name as product_name, p.delivery_type, p.universal_content 
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

<div class="flex flex-col lg:flex-row gap-6 h-[calc(100vh-140px)]">
    
    <!-- LEFT COLUMN: Order List -->
    <div class="<?php echo $active_chat_id ? 'hidden lg:block lg:w-1/3' : 'w-full'; ?> overflow-y-auto custom-scrollbar pr-2 space-y-4">
        <h2 class="text-2xl font-bold mb-4 sticky top-0 bg-[#111827] z-10 py-2 border-b border-gray-800">My Orders</h2>
        
        <?php if(empty($orders)): ?>
            <div class="glass p-8 rounded-xl text-center border border-gray-700">
                <i class="fas fa-shopping-bag text-4xl text-gray-600 mb-3"></i>
                <p class="text-gray-400">You haven't bought anything yet.</p>
                <a href="index.php" class="inline-block mt-4 text-blue-400 hover:text-white text-sm font-bold">Browse Store</a>
            </div>
        <?php else: ?>
            <?php foreach($orders as $order): ?>
                <?php $isActive = ($active_chat_id == $order['id']); ?>
                <div onclick="window.location.href='index.php?module=user&page=orders&view_chat=<?php echo $order['id']; ?>'" 
                     class="cursor-pointer p-4 rounded-xl border transition group relative overflow-hidden
                     <?php echo $isActive ? 'bg-blue-900/20 border-blue-500/50 ring-1 ring-blue-500/30' : 'glass border-gray-700 hover:border-gray-500 hover:bg-gray-800'; ?>">
                    
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="font-bold text-white truncate pr-2"><?php echo htmlspecialchars($order['product_name']); ?></h3>
                        <?php if($order['status'] == 'active'): ?>
                            <span class="text-[10px] font-bold bg-green-500/20 text-green-400 px-2 py-0.5 rounded uppercase tracking-wider">Active</span>
                        <?php elseif($order['status'] == 'pending'): ?>
                            <span class="text-[10px] font-bold bg-yellow-500/20 text-yellow-400 px-2 py-0.5 rounded uppercase tracking-wider">Pending</span>
                        <?php else: ?>
                            <span class="text-[10px] font-bold bg-red-500/20 text-red-400 px-2 py-0.5 rounded uppercase tracking-wider">Rejected</span>
                        <?php endif; ?>
                    </div>

                    <div class="flex justify-between items-end text-xs text-gray-400">
                        <div>
                            <p>Order #<?php echo $order['id']; ?></p>
                            <p class="mt-0.5"><?php echo date('M d', strtotime($order['created_at'])); ?></p>
                        </div>
                        <div class="flex items-center gap-2">
                            <?php if($order['delivery_type'] == 'universal'): ?>
                                <i class="fas fa-bolt text-yellow-500" title="Instant Delivery"></i>
                            <?php endif; ?>
                            <span class="font-bold text-gray-300"><?php echo format_price($order['total_price_paid']); ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- RIGHT COLUMN: Chat & Delivery -->
    <?php if($active_chat_id && $active_order): ?>
    <div class="w-full lg:w-2/3 glass rounded-xl border border-gray-700 flex flex-col overflow-hidden shadow-2xl relative">
        
        <!-- Mobile Back Button -->
        <div class="lg:hidden p-2 border-b border-gray-700">
            <a href="index.php?module=user&page=orders" class="text-sm text-gray-400 flex items-center gap-2"><i class="fas fa-arrow-left"></i> Back to Orders</a>
        </div>

        <!-- Chat Header -->
        <div class="p-4 border-b border-gray-700 bg-gray-800/50 flex justify-between items-center shrink-0">
            <div>
                <h3 class="font-bold text-white text-sm md:text-base">Order #<?php echo $active_order['id']; ?> Support</h3>
                <p class="text-xs text-gray-400"><?php echo htmlspecialchars($active_order['product_name']); ?></p>
            </div>
            
            <!-- Delivery Badge -->
            <?php if($active_order['status'] == 'active'): ?>
                <div class="text-xs bg-green-900/30 text-green-400 border border-green-500/30 px-3 py-1 rounded-full flex items-center gap-2">
                    <i class="fas fa-check-circle"></i> Completed
                </div>
            <?php else: ?>
                <div class="text-xs bg-yellow-900/30 text-yellow-400 border border-yellow-500/30 px-3 py-1 rounded-full flex items-center gap-2">
                    <i class="fas fa-clock"></i> Processing
                </div>
            <?php endif; ?>
        </div>

        <!-- AUTO-DELIVERY BOX (Universal Products) -->
        <?php if($active_order['delivery_type'] == 'universal' && $active_order['status'] == 'active'): ?>
            <div class="bg-blue-900/20 border-b border-blue-500/30 p-4">
                <h4 class="text-xs font-bold text-blue-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                    <i class="fas fa-box-open"></i> Delivered Content
                </h4>
                <div class="bg-gray-900 rounded p-3 border border-gray-700 relative group">
                    <code class="text-sm text-white font-mono break-all"><?php echo nl2br(htmlspecialchars($active_order['universal_content'])); ?></code>
                    <button onclick="navigator.clipboard.writeText(`<?php echo addslashes($active_order['universal_content']); ?>`); alert('Copied!');" 
                            class="absolute top-2 right-2 text-gray-500 hover:text-white opacity-0 group-hover:opacity-100 transition">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <!-- Messages Area -->
        <div class="flex-grow overflow-y-auto p-4 space-y-4 bg-gray-900/50 scroll-smooth" id="chatBox">
            <!-- System Welcome Message -->
            <div class="flex justify-center">
                <div class="bg-gray-800 text-gray-400 text-xs px-3 py-1 rounded-full border border-gray-700">
                    Chat started. Admin will verify your payment shortly.
                </div>
            </div>

            <?php foreach($chat_messages as $msg): ?>
                <div class="flex <?php echo $msg['sender_type'] == 'user' ? 'justify-end' : 'justify-start'; ?>">
                    <div class="max-w-[85%] <?php echo $msg['sender_type'] == 'user' ? 'items-end' : 'items-start'; ?> flex flex-col">
                        
                        <div class="p-3 rounded-2xl text-sm shadow-sm <?php echo $msg['sender_type'] == 'user' ? 'bg-blue-600 text-white rounded-br-none' : 'bg-gray-700 text-gray-200 rounded-bl-none border border-gray-600'; ?>">
                            
                            <?php if($msg['is_credential']): ?>
                                <!-- Credential Style -->
                                <div class="flex items-center gap-2 text-xs font-bold text-green-300 mb-2 border-b border-white/20 pb-1">
                                    <i class="fas fa-key"></i> SECRET DATA
                                </div>
                                <div class="font-mono text-xs whitespace-pre-wrap select-all bg-black/20 p-2 rounded border border-white/10"><?php echo htmlspecialchars($msg['message']); ?></div>
                                <button onclick="navigator.clipboard.writeText(`<?php echo addslashes($msg['message']); ?>`); alert('Copied!');" class="text-xs text-green-300 mt-2 hover:text-white underline">Copy</button>
                            <?php else: ?>
                                <!-- Normal Text -->
                                <div class="whitespace-pre-wrap"><?php echo htmlspecialchars($msg['message']); ?></div>
                            <?php endif; ?>
                            
                        </div>
                        
                        <span class="text-[10px] text-gray-500 mt-1 px-1">
                            <?php echo $msg['sender_type'] == 'user' ? 'You' : 'Admin'; ?> • <?php echo date('H:i', strtotime($msg['created_at'])); ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Input Area -->
        <form method="POST" class="p-4 border-t border-gray-700 bg-gray-800 shrink-0">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="order_id" value="<?php echo $active_chat_id; ?>">
            
            <div class="relative">
                <input type="text" name="message" placeholder="Type a message..." required autocomplete="off"
                       class="w-full bg-gray-900 border border-gray-600 rounded-full py-3 pl-5 pr-12 text-sm text-white focus:border-blue-500 focus:outline-none transition">
                
                <button type="submit" class="absolute right-1 top-1 bg-blue-600 hover:bg-blue-500 text-white w-10 h-10 rounded-full flex items-center justify-center shadow-lg transition transform active:scale-90">
                    <i class="fas fa-paper-plane text-xs"></i>
                </button>
            </div>
        </form>
    </div>
    <script>
        // Auto-scroll chat
        const chatBox = document.getElementById('chatBox');
        if(chatBox) chatBox.scrollTop = chatBox.scrollHeight;
    </script>
    <?php elseif(empty($orders)): ?>
        <!-- Already handled in Left Column empty state -->
    <?php else: ?>
        <!-- Desktop Empty State -->
        <div class="hidden lg:flex w-2/3 glass rounded-xl border border-gray-700 items-center justify-center flex-col text-gray-500">
            <div class="w-20 h-20 bg-gray-800 rounded-full flex items-center justify-center mb-4">
                <i class="far fa-comments text-4xl opacity-50"></i>
            </div>
            <p>Select an order to view details & chat</p>
        </div>
    <?php endif; ?>

</div>