<?php
// admin/order_detail.php

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 1. Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update Status
    if (isset($_POST['update_status'])) {
        $status = $_POST['status'];
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $order_id]);
        
        // If approved and unique key, mark key as sold (Optional automation logic here)
        
        redirect(admin_url('order_detail', ['id' => $order_id]));
    }
    
    // Send Chat Message
    if (isset($_POST['message'])) {
        $msg = trim($_POST['message']);
        $is_cred = isset($_POST['is_credential']) ? 1 : 0;
        
        if (!empty($msg)) {
            $stmt = $pdo->prepare("INSERT INTO order_messages (order_id, sender_type, message, is_credential) VALUES (?, 'admin', ?, ?)");
            $stmt->execute([$order_id, $msg, $is_cred]);
        }
        redirect(admin_url('order_detail', ['id' => $order_id]));
    }
}

// 2. Fetch Order Data
$stmt = $pdo->prepare("
    SELECT o.*, u.username, u.email as user_email, u.phone, 
           p.name as product_name, p.price, p.delivery_type, p.universal_content, p.id as product_id
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    JOIN products p ON o.product_id = p.id 
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    echo "<div class='p-6 bg-red-500/20 text-red-400 rounded-xl border border-red-500/50'>Order #$order_id not found.</div>";
    return;
}

// 3. Fetch Chat History
$stmt = $pdo->prepare("SELECT * FROM order_messages WHERE order_id = ? ORDER BY created_at ASC");
$stmt->execute([$order_id]);
$messages = $stmt->fetchAll();

// 4. Fetch Available Keys (If Unique Type) - For Admin Convenience
$available_keys = [];
if ($order['delivery_type'] === 'unique') {
    $stmt = $pdo->prepare("SELECT key_content FROM product_keys WHERE product_id = ? AND is_sold = 0 LIMIT 5");
    $stmt->execute([$order['product_id']]);
    $available_keys = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>

<div class="max-w-7xl mx-auto h-[calc(100vh-140px)] grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    <!-- LEFT COLUMN: Order Info, Status, Proof -->
    <div class="lg:col-span-1 space-y-6 overflow-y-auto pr-2 custom-scrollbar">
        
        <!-- Main Info Card -->
        <div class="bg-slate-800 p-6 rounded-xl border border-slate-700 shadow-lg">
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h2 class="text-xl font-bold text-white flex items-center gap-2">
                        Order #<?php echo $order['id']; ?>
                    </h2>
                    <p class="text-xs text-slate-400 mt-1"><?php echo date('F j, Y • g:i A', strtotime($order['created_at'])); ?></p>
                </div>
                <a href="<?php echo admin_url('orders'); ?>" class="text-xs bg-slate-700 hover:bg-slate-600 px-3 py-1 rounded text-white transition">Back</a>
            </div>

            <div class="space-y-4 text-sm">
                <div class="flex justify-between border-b border-slate-700/50 pb-2">
                    <span class="text-slate-500">Product</span>
                    <span class="font-medium text-white text-right"><?php echo htmlspecialchars($order['product_name']); ?></span>
                </div>
                <div class="flex justify-between border-b border-slate-700/50 pb-2">
                    <span class="text-slate-500">Customer</span>
                    <div class="text-right">
                        <div class="text-white"><?php echo htmlspecialchars($order['username']); ?></div>
                        <div class="text-xs text-slate-500"><?php echo htmlspecialchars($order['user_email']); ?></div>
                    </div>
                </div>
                <div class="flex justify-between border-b border-slate-700/50 pb-2">
                    <span class="text-slate-500">Total Paid</span>
                    <span class="font-bold text-green-400 text-lg"><?php echo format_admin_currency($order['total_price_paid']); ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-500">Transaction ID</span>
                    <code class="bg-slate-900 px-2 py-1 rounded text-yellow-500 font-mono select-all"><?php echo $order['transaction_last_6']; ?></code>
                </div>
            </div>

            <!-- Status Manager -->
            <form method="POST" class="mt-6 pt-6 border-t border-slate-700">
                <label class="text-xs font-bold text-slate-400 uppercase mb-2 block">Update Status</label>
                <div class="flex gap-2">
                    <select name="status" class="flex-1 bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm focus:border-blue-500 outline-none">
                        <option value="pending" <?php echo $order['status']=='pending'?'selected':''; ?>>Pending</option>
                        <option value="active" <?php echo $order['status']=='active'?'selected':''; ?>>Active (Approved)</option>
                        <option value="rejected" <?php echo $order['status']=='rejected'?'selected':''; ?>>Rejected</option>
                    </select>
                    <button type="submit" name="update_status" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-bold text-sm transition shadow-lg">
                        Save
                    </button>
                </div>
            </form>
        </div>

        <!-- Dynamic Data Display (Based on Type) -->
        <?php if($order['delivery_type'] === 'form' && $order['form_data']): ?>
            <div class="bg-slate-800 p-5 rounded-xl border border-slate-700">
                <h3 class="font-bold text-blue-400 mb-3 text-sm uppercase flex items-center gap-2">
                    <i class="fas fa-database"></i> Submitted Data
                </h3>
                <div class="space-y-2">
                    <?php 
                        $formData = json_decode($order['form_data'], true);
                        if(is_array($formData)) {
                            foreach($formData as $key => $val): 
                    ?>
                        <div class="bg-slate-900 p-2.5 rounded border border-slate-700/50">
                            <div class="text-[10px] text-slate-500 uppercase tracking-wider mb-1"><?php echo htmlspecialchars($key); ?></div>
                            <div class="text-sm text-white select-all font-mono break-all"><?php echo htmlspecialchars($val); ?></div>
                        </div>
                    <?php 
                            endforeach; 
                        }
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Payment Proof -->
        <div class="bg-slate-800 p-4 rounded-xl border border-slate-700">
            <h3 class="font-bold text-slate-300 mb-3 text-sm">Payment Screenshot</h3>
            <?php if($order['proof_image_path']): ?>
                <a href="../<?php echo $order['proof_image_path']; ?>" target="_blank" class="block group relative overflow-hidden rounded-lg border border-slate-600">
                    <img src="../<?php echo $order['proof_image_path']; ?>" class="w-full h-auto object-contain bg-black max-h-64">
                    <div class="absolute inset-0 bg-black/60 flex items-center justify-center opacity-0 group-hover:opacity-100 transition duration-300">
                        <span class="text-white font-bold text-sm"><i class="fas fa-search-plus mr-1"></i> Zoom Image</span>
                    </div>
                </a>
            <?php else: ?>
                <div class="p-4 bg-red-500/10 text-red-400 text-sm rounded border border-red-500/20 text-center">No image uploaded</div>
            <?php endif; ?>
        </div>

    </div>

    <!-- RIGHT COLUMN: Chat & Fulfillment Resources -->
    <div class="lg:col-span-2 flex flex-col gap-6 h-full overflow-hidden">
        
        <!-- Admin Resources Panel (Helper for Fulfillment) -->
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-4 shrink-0">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2 flex items-center gap-2">
                <i class="fas fa-tools"></i> Fulfillment Resources
            </h3>
            
            <?php if($order['delivery_type'] === 'unique'): ?>
                <!-- Unique Keys Helper -->
                <div class="flex gap-2 overflow-x-auto pb-2 custom-scrollbar">
                    <?php if(empty($available_keys)): ?>
                        <span class="text-red-400 text-sm italic">No keys in stock! Add more in Products.</span>
                    <?php else: ?>
                        <?php foreach($available_keys as $k): ?>
                            <div class="bg-slate-900 border border-slate-600 rounded px-3 py-2 min-w-[200px] flex justify-between items-center group">
                                <code class="text-xs text-green-400 font-mono truncate mr-2"><?php echo htmlspecialchars($k); ?></code>
                                <button onclick="navigator.clipboard.writeText('<?php echo addslashes($k); ?>'); alert('Key copied!');" class="text-slate-500 hover:text-white transition" title="Copy Key">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php elseif($order['delivery_type'] === 'universal'): ?>
                <!-- Universal Content Helper -->
                <div class="bg-slate-900 border border-slate-600 rounded p-3 flex justify-between items-start">
                    <div class="text-xs text-slate-300 font-mono break-all line-clamp-2"><?php echo htmlspecialchars($order['universal_content']); ?></div>
                    <button onclick="navigator.clipboard.writeText('<?php echo addslashes($order['universal_content']); ?>'); alert('Content copied!');" class="text-blue-400 hover:text-white ml-3 text-xs font-bold shrink-0">
                        Copy Content
                    </button>
                </div>
            <?php else: ?>
                <div class="text-sm text-slate-500 italic">This is a 'Form' type order. Review customer data on the left.</div>
            <?php endif; ?>
        </div>

        <!-- Chat System -->
        <div class="flex-grow bg-slate-800 rounded-xl border border-slate-700 flex flex-col shadow-xl overflow-hidden">
            <!-- Chat Header -->
            <div class="p-4 border-b border-slate-700 bg-slate-700/20 flex justify-between items-center shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-blue-600 to-purple-600 flex items-center justify-center text-white font-bold">
                        <?php echo strtoupper(substr($order['username'], 0, 1)); ?>
                    </div>
                    <div>
                        <h3 class="font-bold text-white text-sm">Chat with <?php echo htmlspecialchars($order['username']); ?></h3>
                        <p class="text-xs text-green-400 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span> Online</p>
                    </div>
                </div>
                <div class="text-xs text-slate-500">
                    <?php echo $order['email_delivery_type'] == 'own' ? 'Email: '.htmlspecialchars($order['delivery_email']) : 'Admin Account Request'; ?>
                </div>
            </div>

            <!-- Messages List -->
            <div class="flex-grow overflow-y-auto p-6 space-y-4 bg-slate-900/50 scroll-smooth" id="chatBox">
                <?php if(empty($messages)): ?>
                    <div class="flex flex-col items-center justify-center h-full text-slate-600">
                        <i class="far fa-comments text-5xl mb-3 opacity-30"></i>
                        <p class="text-sm">Start the conversation to fulfill the order.</p>
                    </div>
                <?php endif; ?>

                <?php foreach($messages as $msg): ?>
                    <div class="flex <?php echo $msg['sender_type'] == 'admin' ? 'justify-end' : 'justify-start'; ?>">
                        <div class="max-w-[85%] <?php echo $msg['sender_type'] == 'admin' ? 'items-end' : 'items-start'; ?> flex flex-col">
                            <div class="p-3 rounded-2xl text-sm shadow-sm <?php echo $msg['sender_type'] == 'admin' ? 'bg-blue-600 text-white rounded-br-none' : 'bg-slate-700 text-slate-200 rounded-bl-none border border-slate-600'; ?>">
                                <?php if($msg['is_credential']): ?>
                                    <div class="flex items-center gap-2 text-xs font-bold text-green-300 mb-2 border-b border-white/20 pb-1">
                                        <i class="fas fa-key"></i> SECURE CREDENTIAL
                                    </div>
                                    <div class="font-mono text-xs whitespace-pre-wrap select-all bg-black/20 p-2 rounded border border-white/10"><?php echo htmlspecialchars($msg['message']); ?></div>
                                <?php else: ?>
                                    <div class="whitespace-pre-wrap"><?php echo htmlspecialchars($msg['message']); ?></div>
                                <?php endif; ?>
                            </div>
                            <span class="text-[10px] text-slate-500 mt-1 px-1">
                                <?php echo date('H:i', strtotime($msg['created_at'])); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Input Area -->
            <form method="POST" class="p-4 border-t border-slate-700 bg-slate-800 shrink-0">
                <div class="relative">
                    <textarea name="message" rows="1" placeholder="Type message..." class="w-full bg-slate-900 border border-slate-600 rounded-xl py-3 pl-4 pr-32 text-sm text-white focus:border-blue-500 outline-none resize-none custom-scrollbar" style="min-height: 50px; max-height: 120px;" oninput="this.style.height = ''; this.style.height = this.scrollHeight + 'px'"></textarea>
                    
                    <div class="absolute right-2 bottom-2 flex items-center gap-2">
                        <label class="cursor-pointer text-slate-400 hover:text-green-400 transition p-2 rounded hover:bg-slate-700" title="Send as Credential">
                            <input type="checkbox" name="is_credential" value="1" class="hidden peer">
                            <i class="fas fa-key peer-checked:text-green-500"></i>
                        </label>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white w-9 h-9 rounded-lg flex items-center justify-center shadow-lg transition">
                            <i class="fas fa-paper-plane text-xs"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Auto-scroll chat
    const chatBox = document.getElementById('chatBox');
    if(chatBox) chatBox.scrollTop = chatBox.scrollHeight;
</script>