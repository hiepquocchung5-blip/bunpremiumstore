<?php
// admin/order_detail.php

// Include Notification Services
require_once dirname(__DIR__) . '/includes/MailService.php';
require_once dirname(__DIR__) . '/includes/PushService.php';

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 1. Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ACTION: UPDATE STATUS
    if (isset($_POST['update_status'])) {
        $status = $_POST['status'];
        
        // Update DB
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $order_id]);
        
        // Fetch User Info for Notification
        $stmt = $pdo->prepare("
            SELECT o.user_id, o.email_delivery_type, o.delivery_email, p.name as product_name, o.total_price_paid 
            FROM orders o
            JOIN products p ON o.product_id = p.id
            WHERE o.id = ?
        ");
        $stmt->execute([$order_id]);
        $ord_meta = $stmt->fetch();
        
        // 🚀 AUTOMATION: Send Notifications
        if ($ord_meta) {
            $push = new PushService($pdo);
            $mail = new MailService(); 
            
            $status_title = "Order Updated";
            $status_msg = "Your order #$order_id status is now: " . ucfirst($status);
            $url = BASE_URL . "index.php?module=user&page=orders&view_chat=$order_id";

            if ($status === 'active') {
                $status_title = "Order Complete! ✅";
                $status_msg = "Your order #$order_id is ready! Check your account for details.";
                
                // Send Email Confirmation
                $user_email = $ord_meta['email_delivery_type'] == 'own' ? $ord_meta['delivery_email'] : ''; // Or fetch user's main email
                if($user_email) {
                    // Logic to fetch main user email if needed, simplified here
                }
            } elseif ($status === 'rejected') {
                $status_title = "Order Rejected ❌";
                $status_msg = "There was an issue with order #$order_id. Please check support chat.";
            }

            // Send Push
            $push->sendToUser($ord_meta['user_id'], $status_title, $status_msg, $url);
        }
        
        redirect(admin_url('order_detail', ['id' => $order_id]));
    }
    
    // ACTION: SEND CHAT MESSAGE
    if (isset($_POST['message'])) {
        $msg = trim($_POST['message']);
        $is_cred = isset($_POST['is_credential']) ? 1 : 0;
        
        if (!empty($msg)) {
            $stmt = $pdo->prepare("INSERT INTO order_messages (order_id, sender_type, message, is_credential) VALUES (?, 'admin', ?, ?)");
            $stmt->execute([$order_id, $msg, $is_cred]);

            // Notify User
            $stmt = $pdo->prepare("SELECT user_id FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $uid = $stmt->fetchColumn();
            
            $push = new PushService($pdo);
            $push->sendToUser($uid, "New Message 💬", "Support sent a message about Order #$order_id");
        }
        
        redirect(admin_url('order_detail', ['id' => $order_id]));
    }
}

// 2. Fetch Full Order Data
$stmt = $pdo->prepare("
    SELECT o.*, u.username, u.email as user_account_email, u.phone, 
           p.name as product_name, p.price, p.delivery_type, p.universal_content, p.id as product_id, p.image_path as product_image
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    JOIN products p ON o.product_id = p.id 
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    echo "<div class='p-6 bg-red-500/20 text-red-400 rounded-xl border border-red-500/50'>Order #$order_id not found. <a href='".admin_url('orders')."' class='underline'>Back to List</a></div>";
    return;
}

// 3. Fetch Chat History
$stmt = $pdo->prepare("SELECT * FROM order_messages WHERE order_id = ? ORDER BY created_at ASC");
$stmt->execute([$order_id]);
$messages = $stmt->fetchAll();

// 4. Fetch Available Keys (Inventory Helper for Unique Products)
$available_keys = [];
if ($order['delivery_type'] === 'unique') {
    $stmt = $pdo->prepare("SELECT key_content FROM product_keys WHERE product_id = ? AND is_sold = 0 LIMIT 10");
    $stmt->execute([$order['product_id']]);
    $available_keys = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>

<style>
    /* Lightbox Styles */
    .lightbox { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.9); backdrop-filter: blur(5px); justify-content: center; align-items: center; }
    .lightbox-content { max-width: 90%; max-height: 90%; border-radius: 5px; box-shadow: 0 0 20px rgba(0,0,0,0.5); }
    .close-lightbox { position: absolute; top: 20px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; cursor: pointer; }
</style>

<div class="max-w-7xl mx-auto h-[calc(100vh-140px)] grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    <!-- LEFT COLUMN: Order Info & Proof -->
    <div class="lg:col-span-1 space-y-6 overflow-y-auto pr-2 custom-scrollbar">
        
        <!-- Header Card -->
        <div class="bg-slate-800 p-6 rounded-xl border border-slate-700 shadow-lg relative overflow-hidden">
            <div class="flex justify-between items-start mb-6 relative z-10">
                <div>
                    <h2 class="text-xl font-bold text-white flex items-center gap-2">
                        Order #<?php echo $order['id']; ?>
                    </h2>
                    <p class="text-xs text-slate-400 mt-1 flex items-center gap-1">
                        <i class="far fa-clock"></i> <?php echo date('F j, Y • g:i A', strtotime($order['created_at'])); ?>
                    </p>
                </div>
                <a href="<?php echo admin_url('orders'); ?>" class="text-xs bg-slate-700 hover:bg-slate-600 px-3 py-1.5 rounded-lg text-white transition border border-slate-600">
                    <i class="fas fa-arrow-left mr-1"></i> Back
                </a>
            </div>

            <!-- Product Mini Info -->
            <div class="flex items-center gap-4 mb-6 p-3 bg-slate-900/50 rounded-lg border border-slate-700/50">
                <div class="w-12 h-12 bg-slate-800 rounded-lg flex items-center justify-center shrink-0 overflow-hidden">
                    <?php if($order['product_image']): ?>
                        <img src="<?php echo MAIN_SITE_URL . $order['product_image']; ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <i class="fas fa-cube text-slate-500"></i>
                    <?php endif; ?>
                </div>
                <div class="min-w-0">
                    <div class="text-white font-bold text-sm truncate"><?php echo htmlspecialchars($order['product_name']); ?></div>
                    <div class="flex gap-2 mt-1">
                        <span class="text-[10px] text-slate-300 uppercase bg-slate-700 px-1.5 py-0.5 rounded"><?php echo $order['delivery_type']; ?></span>
                        <span class="text-[10px] text-green-400 font-mono font-bold bg-green-900/20 px-1.5 py-0.5 rounded border border-green-900/30"><?php echo format_admin_currency($order['total_price_paid']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Status Manager -->
            <form method="POST" class="space-y-3">
                <label class="text-xs font-bold text-slate-400 uppercase tracking-wider">Order Status</label>
                <div class="flex gap-2">
                    <select name="status" class="flex-1 bg-slate-900 border border-slate-600 rounded-lg px-3 py-2.5 text-white text-sm focus:border-blue-500 outline-none cursor-pointer">
                        <option value="pending" <?php echo $order['status']=='pending'?'selected':''; ?>>🟡 Pending</option>
                        <option value="active" <?php echo $order['status']=='active'?'selected':''; ?>>🟢 Active (Approve)</option>
                        <option value="rejected" <?php echo $order['status']=='rejected'?'selected':''; ?>>🔴 Rejected</option>
                    </select>
                    <button type="submit" name="update_status" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg font-bold text-sm transition shadow-lg flex items-center">
                        <i class="fas fa-save mr-2"></i> Save
                    </button>
                </div>
            </form>
        </div>

        <!-- Customer & Payment Data -->
        <div class="bg-slate-800 p-6 rounded-xl border border-slate-700 shadow-lg space-y-4">
            <h3 class="font-bold text-slate-200 text-sm uppercase border-b border-slate-700 pb-2 mb-2">Customer & Payment</h3>
            
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="block text-slate-500 text-xs">Username</span>
                    <span class="text-white font-medium"><?php echo htmlspecialchars($order['username']); ?></span>
                </div>
                <div>
                    <span class="block text-slate-500 text-xs">Txn Last 6</span>
                    <span class="font-mono text-yellow-500 bg-slate-900 px-2 py-0.5 rounded select-all"><?php echo $order['transaction_last_6']; ?></span>
                </div>
                <div class="col-span-2">
                    <span class="block text-slate-500 text-xs mb-1">Delivery Email</span>
                    <div class="flex gap-2">
                        <input type="text" readonly value="<?php echo htmlspecialchars($order['delivery_email'] ?: 'Admin Provided Account'); ?>" class="bg-slate-900 border border-slate-700 rounded px-2 py-1 text-slate-300 text-xs flex-1 outline-none">
                        <button type="button" onclick="navigator.clipboard.writeText('<?php echo addslashes($order['delivery_email']); ?>'); alert('Email Copied');" class="text-slate-400 hover:text-white"><i class="fas fa-copy"></i></button>
                    </div>
                </div>
            </div>

            <?php if($order['form_data']): ?>
                <div class="mt-4 pt-4 border-t border-slate-700">
                    <span class="block text-blue-400 text-xs font-bold uppercase mb-2">Submitted Data</span>
                    <div class="space-y-2">
                    <?php 
                        $formData = json_decode($order['form_data'], true);
                        foreach($formData as $key => $val): 
                    ?>
                        <div class="bg-slate-900 p-2 rounded border border-slate-700/50">
                            <div class="text-xs text-slate-500 mb-1"><?php echo htmlspecialchars($key); ?>:</div>
                            <div class="text-sm text-white font-mono select-all"><?php echo htmlspecialchars($val); ?></div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Payment Proof -->
        <div class="bg-slate-800 p-4 rounded-xl border border-slate-700 shadow-lg">
            <h3 class="font-bold text-slate-300 mb-3 text-sm">Payment Screenshot</h3>
            <?php if($order['proof_image_path']): ?>
                <div class="relative group overflow-hidden rounded-lg border border-slate-600 cursor-zoom-in" 
                     onclick="openLightbox('<?php echo MAIN_SITE_URL . $order['proof_image_path']; ?>')">
                    <img src="<?php echo MAIN_SITE_URL . $order['proof_image_path']; ?>" class="w-full h-auto max-h-60 object-contain bg-black">
                    <div class="absolute inset-0 bg-black/60 flex items-center justify-center opacity-0 group-hover:opacity-100 transition duration-300">
                        <span class="text-white font-bold text-xs flex items-center gap-2 bg-slate-800/80 px-3 py-1.5 rounded-full backdrop-blur-sm"><i class="fas fa-search-plus"></i> View Fullsize</span>
                    </div>
                </div>
            <?php else: ?>
                <div class="p-4 bg-red-500/10 text-red-400 text-sm rounded border border-red-500/20 text-center">No image uploaded</div>
            <?php endif; ?>
        </div>

    </div>

    <!-- RIGHT COLUMN: Chat & Fulfillment -->
    <div class="lg:col-span-2 flex flex-col gap-6 h-full overflow-hidden">
        
        <!-- Fulfillment Helper Panel -->
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-4 shrink-0 shadow-lg">
            <div class="flex justify-between items-center mb-3">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center gap-2">
                    <i class="fas fa-tools text-blue-500"></i> Fulfillment Helper
                </h3>
                <span class="text-[10px] text-slate-500 bg-slate-900 px-2 py-0.5 rounded border border-slate-700">Type: <?php echo ucfirst($order['delivery_type']); ?></span>
            </div>
            
            <?php if($order['delivery_type'] === 'unique'): ?>
                <!-- Unique Keys Helper -->
                <div class="flex gap-3 overflow-x-auto pb-2 custom-scrollbar">
                    <?php if(empty($available_keys)): ?>
                        <div class="w-full p-3 bg-red-900/20 border border-red-500/30 rounded-lg text-red-300 text-sm flex items-center gap-2">
                            <i class="fas fa-exclamation-circle"></i> No keys in stock!
                            <a href="<?php echo admin_url('keys', ['product_id' => $order['product_id']]); ?>" class="underline font-bold hover:text-white">Add Keys</a>
                        </div>
                    <?php else: ?>
                        <?php foreach($available_keys as $k): ?>
                            <div class="bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 min-w-[220px] flex justify-between items-center group hover:border-blue-500/50 transition">
                                <code class="text-xs text-green-400 font-mono truncate mr-2"><?php echo htmlspecialchars($k); ?></code>
                                <button onclick="navigator.clipboard.writeText('<?php echo addslashes($k); ?>'); alert('Key copied to clipboard!');" class="text-slate-500 hover:text-white bg-slate-800 p-1.5 rounded transition" title="Copy Key">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php elseif($order['delivery_type'] === 'universal'): ?>
                <!-- Universal Content Helper -->
                <div class="bg-slate-900 border border-slate-600 rounded-lg p-3 flex justify-between items-start group hover:border-green-500/50 transition">
                    <div class="text-xs text-slate-300 font-mono break-all line-clamp-2"><?php echo htmlspecialchars($order['universal_content']); ?></div>
                    <button onclick="navigator.clipboard.writeText('<?php echo addslashes($order['universal_content']); ?>'); alert('Content copied!');" class="text-blue-400 hover:text-white ml-3 text-xs font-bold shrink-0 bg-slate-800 px-3 py-1.5 rounded transition">
                        Copy Content
                    </button>
                </div>
            <?php else: ?>
                <div class="text-sm text-slate-500 italic bg-slate-900/50 p-2 rounded border border-slate-700/50 text-center">
                    This is a 'Form' product. Review the "Submitted Data" on the left panel to manually fulfill this order.
                </div>
            <?php endif; ?>
        </div>

        <!-- Chat System -->
        <div class="flex-grow bg-slate-800 rounded-xl border border-slate-700 flex flex-col shadow-xl overflow-hidden relative">
            <!-- Chat Header -->
            <div class="p-4 border-b border-slate-700 bg-slate-700/20 flex justify-between items-center shrink-0 backdrop-blur-sm z-10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-blue-600 to-purple-600 flex items-center justify-center text-white font-bold shadow-md">
                        <?php echo strtoupper(substr($order['username'], 0, 1)); ?>
                    </div>
                    <div>
                        <h3 class="font-bold text-white text-sm">Chat with <?php echo htmlspecialchars($order['username']); ?></h3>
                        <p class="text-[10px] text-green-400 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span> Online</p>
                    </div>
                </div>
                <div class="text-xs text-slate-500">
                    <?php echo $order['email_delivery_type'] == 'own' ? 'Email: '.htmlspecialchars($order['delivery_email']) : 'Requested Admin Account'; ?>
                </div>
            </div>

            <!-- Messages List -->
            <div class="flex-grow overflow-y-auto p-6 space-y-4 bg-slate-900/50 scroll-smooth custom-scrollbar" id="chatBox">
                <?php if(empty($messages)): ?>
                    <div class="flex flex-col items-center justify-center h-full text-slate-600">
                        <i class="far fa-comments text-5xl mb-3 opacity-30"></i>
                        <p class="text-sm font-medium">No messages yet.</p>
                        <p class="text-xs">Send a message to start the conversation.</p>
                    </div>
                <?php endif; ?>

                <?php foreach($messages as $msg): ?>
                    <div class="flex <?php echo $msg['sender_type'] == 'admin' ? 'justify-end' : 'justify-start'; ?>">
                        <div class="max-w-[85%] flex flex-col <?php echo $msg['sender_type'] == 'admin' ? 'items-end' : 'items-start'; ?>">
                            
                            <div class="p-3 rounded-2xl text-sm shadow-sm relative <?php echo $msg['sender_type'] == 'admin' ? 'bg-blue-600 text-white rounded-br-none' : 'bg-slate-700 text-slate-200 rounded-bl-none border border-slate-600'; ?>">
                                
                                <?php if($msg['is_credential']): ?>
                                    <div class="flex items-center gap-2 text-xs font-bold text-green-300 mb-2 border-b border-white/20 pb-1">
                                        <i class="fas fa-key"></i> SECURE CREDENTIAL
                                    </div>
                                    <div class="font-mono text-xs whitespace-pre-wrap select-all bg-black/20 p-2 rounded border border-white/10"><?php echo htmlspecialchars($msg['message']); ?></div>
                                <?php else: ?>
                                    <div class="whitespace-pre-wrap leading-relaxed"><?php echo htmlspecialchars($msg['message']); ?></div>
                                <?php endif; ?>
                                
                            </div>
                            
                            <span class="text-[10px] text-slate-500 mt-1 px-1 opacity-70">
                                <?php echo date('H:i', strtotime($msg['created_at'])); ?> • <?php echo $msg['sender_type'] == 'admin' ? 'You' : 'User'; ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Input Area -->
            <form method="POST" class="p-4 border-t border-slate-700 bg-slate-800 shrink-0 z-10">
                <div class="relative group">
                    <textarea name="message" rows="1" placeholder="Type message..." class="w-full bg-slate-900 border border-slate-600 rounded-2xl py-3.5 pl-4 pr-32 text-sm text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none resize-none custom-scrollbar shadow-inner transition-all" style="min-height: 52px; max-height: 120px;" oninput="this.style.height = ''; this.style.height = this.scrollHeight + 'px'"></textarea>
                    
                    <div class="absolute right-2 bottom-2 flex items-center gap-2">
                        <label class="cursor-pointer text-slate-400 hover:text-green-400 transition p-2 rounded-full hover:bg-slate-700/50" title="Send as Secure Credential">
                            <input type="checkbox" name="is_credential" value="1" class="hidden peer">
                            <i class="fas fa-key peer-checked:text-green-500 peer-checked:animate-pulse"></i>
                        </label>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white w-9 h-9 rounded-xl flex items-center justify-center shadow-lg transition transform active:scale-95 group-focus-within:bg-blue-500">
                            <i class="fas fa-paper-plane text-xs"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Lightbox Element -->
<div id="proofLightbox" class="lightbox fixed inset-0 z-50 hidden bg-black/95 backdrop-blur-md flex items-center justify-center p-4" onclick="closeLightbox()">
    <button class="absolute top-5 right-5 text-white/50 hover:text-white transition text-4xl font-bold">&times;</button>
    <img id="lightboxImg" class="max-w-full max-h-[90vh] rounded-lg shadow-2xl transform scale-95 transition duration-300">
</div>

<script>
    // 1. Auto-scroll chat
    const chatBox = document.getElementById('chatBox');
    if(chatBox) {
        chatBox.scrollTop = chatBox.scrollHeight;
        window.addEventListener('resize', () => chatBox.scrollTop = chatBox.scrollHeight);
    }

    // 2. Lightbox Logic
    function openLightbox(src) {
        const lightbox = document.getElementById('proofLightbox');
        const img = document.getElementById('lightboxImg');
        img.src = src;
        lightbox.classList.remove('hidden');
        lightbox.classList.add('flex');
        setTimeout(() => img.classList.remove('scale-95'), 10);
        document.body.style.overflow = "hidden";
    }
    
    function closeLightbox() {
        const lightbox = document.getElementById('proofLightbox');
        const img = document.getElementById('lightboxImg');
        img.classList.add('scale-95');
        setTimeout(() => {
            lightbox.classList.add('hidden');
            lightbox.classList.remove('flex');
        }, 200);
        document.body.style.overflow = "auto";
    }
</script>