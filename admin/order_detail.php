<?php
// admin/order_detail.php

// Include Notification Services (Suppress warnings if files are missing)
@include_once dirname(__DIR__) . '/includes/MailService.php';
@include_once dirname(__DIR__) . '/includes/PushService.php';

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Quick Reply Macros (S-Tier Feature)
$quick_replies = [
    "⏳ Please wait 5-10 mins while we verify your KBZPay/Wave transaction.",
    "✅ Payment verified! Processing your digital goods right now.",
    "🎁 Your order is complete! Thank you for choosing DigitalMarketplaceMM.",
    "⚠️ We couldn't verify the payment. Please send a clearer screenshot.",
    "🛠️ Let me check the system for your key. One moment please.",
    "🎮 Here is your requested code/account. Please read the instructions carefully."
];

// 1. Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ACTION: UPDATE STATUS
    if (isset($_POST['update_status'])) {
        $status = $_POST['status'];
        
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $order_id]);
        
        $stmt = $pdo->prepare("
            SELECT o.user_id, o.email_delivery_type, o.delivery_email, p.name as product_name, o.total_price_paid 
            FROM orders o JOIN products p ON o.product_id = p.id WHERE o.id = ?
        ");
        $stmt->execute([$order_id]);
        $ord_meta = $stmt->fetch();
        
        if ($ord_meta) {
            $status_title = "Order Updated";
            $status_msg = "Your order #$order_id status is now: " . ucfirst($status);
            $url = BASE_URL . "index.php?module=user&page=orders&view_chat=$order_id";

            if ($status === 'active') {
                $status_title = "Order Complete! ✅";
                $status_msg = "Your order #$order_id is ready! Check your account.";
            } elseif ($status === 'rejected') {
                $status_title = "Order Rejected ❌";
                $status_msg = "There was an issue with order #$order_id. Please check support chat.";
            }

            // Safe Execution: Prevent crashes if Push/Mail tables or classes are missing
            try {
                if (class_exists('PushService')) {
                    $push = new PushService($pdo);
                    $push->sendToUser($ord_meta['user_id'], $status_title, $status_msg, $url);
                }
            } catch (Exception $e) { error_log("Push Error: " . $e->getMessage()); }
        }
        // Redirect to refresh page state
        echo "<script>window.location.href='index.php?page=order_detail&id=$order_id';</script>";
        exit;
    }
    
    // ACTION: SEND CHAT MESSAGE
    if (isset($_POST['message'])) {
        $msg = trim($_POST['message']);
        $is_cred = isset($_POST['is_credential']) ? 1 : 0;
        
        if (!empty($msg)) {
            $stmt = $pdo->prepare("INSERT INTO order_messages (order_id, sender_type, message, is_credential) VALUES (?, 'admin', ?, ?)");
            $stmt->execute([$order_id, $msg, $is_cred]);

            $stmt = $pdo->prepare("SELECT user_id FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $uid = $stmt->fetchColumn();
            
            // Safe Execution for Push
            try {
                if (class_exists('PushService')) {
                    $push = new PushService($pdo);
                    $push->sendToUser($uid, "New Message 💬", "Support replied to Order #$order_id");
                }
            } catch (Exception $e) {}
        }
        echo "<script>window.location.href='index.php?page=order_detail&id=$order_id';</script>";
        exit;
    }
}

// 2. Fetch Full Order Data (FIXED: Removed missing image_path, added c.icon_class fallback)
$stmt = $pdo->prepare("
    SELECT o.*, u.username, u.email as user_account_email, u.phone, 
           p.name as product_name, p.price, p.delivery_type, p.universal_content, p.id as product_id,
           c.icon_class
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    JOIN products p ON o.product_id = p.id 
    LEFT JOIN categories c ON p.category_id = c.id
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

// 4. Fetch Available Keys
$available_keys = [];
if ($order['delivery_type'] === 'unique') {
    $stmt = $pdo->prepare("SELECT id, key_content FROM product_keys WHERE product_id = ? AND is_sold = 0 LIMIT 10");
    $stmt->execute([$order['product_id']]);
    $available_keys = $stmt->fetchAll();
}
?>

<style>
    .lightbox { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.9); backdrop-filter: blur(5px); justify-content: center; align-items: center; }
    .lightbox-content { max-width: 90%; max-height: 90%; border-radius: 5px; box-shadow: 0 0 20px rgba(0,240,255,0.2); }
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>

<div class="max-w-7xl mx-auto h-[calc(100vh-140px)] grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    <!-- LEFT COLUMN: Order Info & Proof -->
    <div class="lg:col-span-1 space-y-6 overflow-y-auto pr-2 custom-scrollbar">
        
        <!-- Header Card -->
        <div class="bg-slate-900/80 p-6 rounded-xl border border-[#00f0ff]/20 shadow-[0_0_15px_rgba(0,240,255,0.05)] relative overflow-hidden group hover:border-[#00f0ff]/40 transition-all">
            <div class="absolute -right-10 -top-10 w-32 h-32 bg-[#00f0ff]/10 rounded-full blur-3xl pointer-events-none"></div>
            
            <div class="flex justify-between items-start mb-6 relative z-10">
                <div>
                    <h2 class="text-xl font-bold text-white flex items-center gap-2">
                        Order #<?php echo $order['id']; ?>
                    </h2>
                    <p class="text-xs text-[#00f0ff] mt-1 flex items-center gap-1 font-mono">
                        <i class="far fa-clock"></i> <?php echo date('M j, H:i', strtotime($order['created_at'])); ?>
                    </p>
                </div>
                <a href="index.php?page=orders" class="text-xs bg-slate-800 hover:bg-slate-700 px-3 py-1.5 rounded-lg text-white transition border border-slate-600 shadow-sm">
                    <i class="fas fa-arrow-left"></i>
                </a>
            </div>

            <!-- Product Mini Info -->
            <div class="flex items-center gap-4 mb-6 p-3 bg-slate-800/50 rounded-lg border border-slate-700 relative z-10">
                <div class="w-12 h-12 bg-slate-900 rounded-lg flex items-center justify-center shrink-0 overflow-hidden border border-slate-700 shadow-inner">
                    <?php if(!empty($order['image_path'])): ?>
                        <img src="<?php echo MAIN_SITE_URL . $order['image_path']; ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <i class="fas <?php echo htmlspecialchars($order['icon_class'] ?? 'fa-cube'); ?> text-[#00f0ff] text-xl"></i>
                    <?php endif; ?>
                </div>
                <div class="min-w-0">
                    <div class="text-white font-bold text-sm truncate"><?php echo htmlspecialchars($order['product_name']); ?></div>
                    <div class="flex gap-2 mt-1">
                        <span class="text-[10px] text-slate-300 uppercase bg-slate-700 px-1.5 py-0.5 rounded"><?php echo $order['delivery_type']; ?></span>
                        <span class="text-[10px] text-green-400 font-mono font-bold bg-green-900/20 px-1.5 py-0.5 rounded border border-green-900/30">
                            <?php echo number_format($order['total_price_paid']); ?> Ks
                        </span>
                    </div>
                </div>
            </div>

            <!-- Status Manager -->
            <form method="POST" class="space-y-3 relative z-10">
                <label class="text-xs font-bold text-slate-400 uppercase tracking-wider">Order Status</label>
                <div class="flex gap-2">
                    <select name="status" class="flex-1 bg-slate-800 border border-slate-600 rounded-lg px-3 py-2.5 text-white text-sm focus:border-[#00f0ff] outline-none cursor-pointer shadow-inner">
                        <option value="pending" <?php echo $order['status']=='pending'?'selected':''; ?>>🟡 Pending</option>
                        <option value="active" <?php echo $order['status']=='active'?'selected':''; ?>>🟢 Active (Approve)</option>
                        <option value="rejected" <?php echo $order['status']=='rejected'?'selected':''; ?>>🔴 Rejected</option>
                    </select>
                    <button type="submit" name="update_status" class="bg-gradient-to-r from-blue-600 to-[#00f0ff] hover:from-blue-500 hover:to-[#00f0ff] text-slate-900 px-5 py-2.5 rounded-lg font-bold text-sm transition shadow-lg flex items-center">
                        <i class="fas fa-save mr-2"></i> Save
                    </button>
                </div>
            </form>
        </div>

        <!-- Customer & Payment Data -->
        <div class="bg-slate-800 p-6 rounded-xl border border-slate-700 shadow-lg space-y-4">
            <h3 class="font-bold text-slate-200 text-sm uppercase border-b border-slate-700 pb-2 mb-2">Payment Verification</h3>
            
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="block text-slate-500 text-xs">Customer</span>
                    <span class="text-white font-medium">@<?php echo htmlspecialchars($order['username']); ?></span>
                </div>
                <div>
                    <span class="block text-slate-500 text-xs">Txn ID</span>
                    <span class="font-mono text-yellow-400 bg-yellow-900/20 px-2 py-0.5 rounded border border-yellow-500/30 select-all"><?php echo $order['transaction_last_6']; ?></span>
                </div>
            </div>

            <?php if(!empty($order['form_data'])): ?>
                <div class="mt-4 pt-4 border-t border-slate-700">
                    <span class="block text-[#00f0ff] text-xs font-bold uppercase mb-2">Form Data (User Input)</span>
                    <div class="space-y-2">
                    <?php 
                        $formData = json_decode($order['form_data'], true);
                        if(is_array($formData)) {
                            foreach($formData as $key => $val): 
                    ?>
                        <div class="bg-slate-900 p-2 rounded border border-slate-700/50">
                            <div class="text-xs text-slate-500 mb-1"><?php echo htmlspecialchars($key); ?>:</div>
                            <div class="text-sm text-white font-mono select-all"><?php echo htmlspecialchars($val); ?></div>
                        </div>
                    <?php 
                            endforeach; 
                        } 
                    ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Payment Proof -->
        <div class="bg-slate-800 p-4 rounded-xl border border-slate-700 shadow-lg">
            <h3 class="font-bold text-slate-300 mb-3 text-sm">Transfer Screenshot</h3>
            <?php if(!empty($order['proof_image_path'])): ?>
                <div class="relative group overflow-hidden rounded-lg border border-slate-600 cursor-zoom-in" onclick="openLightbox('<?php echo MAIN_SITE_URL . $order['proof_image_path']; ?>')">
                    <img src="<?php echo MAIN_SITE_URL . $order['proof_image_path']; ?>" class="w-full h-auto max-h-60 object-contain bg-slate-900">
                    <div class="absolute inset-0 bg-[#00f0ff]/10 backdrop-blur-sm flex items-center justify-center opacity-0 group-hover:opacity-100 transition duration-300">
                        <span class="text-white font-bold text-xs flex items-center gap-2 bg-slate-900/80 px-4 py-2 rounded-full border border-[#00f0ff]/50"><i class="fas fa-search-plus"></i> Inspect</span>
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
        <div class="bg-slate-900 border border-[#00f0ff]/30 rounded-xl p-4 shrink-0 shadow-[0_0_15px_rgba(0,240,255,0.05)] relative overflow-hidden">
            <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PHBhdHRlcm4gaWQ9ImdyaWQiIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCIgcGF0dGVyblVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+PHBhdGggZD0iTSAwIDEwIEwgNDAgMTAgTSAxMCAwIEwgMTAgNDAiIGZpbGw9Im5vbmUiIHN0cm9rZT0icmdiYSgwLCAyNDAsIDI1NSwgMC4wNSkiIHN0cm9rZS13aWR0aD0iMSIvPjwvcGF0dGVybj48L2RlZnM+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0idXJsKCNncmlkKSIvPjwvc3ZnPg==')] opacity-50"></div>
            
            <div class="flex justify-between items-center mb-3 relative z-10">
                <h3 class="text-xs font-bold text-[#00f0ff] uppercase tracking-wider flex items-center gap-2">
                    <i class="fas fa-bolt"></i> Auto-Fulfillment Hub
                </h3>
            </div>
            
            <div class="relative z-10">
                <?php if($order['delivery_type'] === 'unique'): ?>
                    <div class="flex gap-3 overflow-x-auto pb-2 no-scrollbar">
                        <?php if(empty($available_keys)): ?>
                            <div class="w-full p-3 bg-red-900/20 border border-red-500/30 rounded-lg text-red-400 text-sm flex items-center gap-2">
                                <i class="fas fa-exclamation-circle"></i> Out of Stock! <a href="index.php?page=keys&product_id=<?php echo $order['product_id']; ?>" class="underline font-bold text-white">Add Keys</a>
                            </div>
                        <?php else: ?>
                            <?php foreach($available_keys as $k): ?>
                                <button type="button" onclick="autoPasteKey('<?php echo addslashes($k['key_content']); ?>')" class="bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 min-w-[200px] flex justify-between items-center group hover:bg-slate-700 hover:border-[#00f0ff] transition cursor-pointer text-left">
                                    <code class="text-xs text-green-400 font-mono truncate mr-2 group-hover:text-[#00f0ff] transition"><?php echo htmlspecialchars($k['key_content']); ?></code>
                                    <i class="fas fa-paper-plane text-slate-500 group-hover:text-[#00f0ff] text-xs"></i>
                                </button>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <p class="text-[10px] text-slate-500 mt-1"><i class="fas fa-info-circle"></i> Click a key to auto-paste into chat securely.</p>
                <?php elseif($order['delivery_type'] === 'universal'): ?>
                    <div class="bg-slate-800 border border-slate-600 rounded-lg p-3 flex justify-between items-start">
                        <div class="text-xs text-slate-300 font-mono break-all line-clamp-2"><?php echo htmlspecialchars($order['universal_content']); ?></div>
                        <button type="button" onclick="autoPasteKey('<?php echo addslashes($order['universal_content']); ?>')" class="bg-slate-700 hover:bg-slate-600 text-[#00f0ff] text-xs font-bold px-3 py-1.5 rounded transition shadow-sm ml-3 border border-slate-600">
                            Paste to Chat
                        </button>
                    </div>
                <?php else: ?>
                    <div class="text-sm text-slate-400 bg-slate-800/50 p-2 rounded border border-slate-700/50 text-center">
                        Manual Fulfillment required based on user's Form Data.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Chat System -->
        <div class="flex-grow bg-slate-800/90 rounded-xl border border-slate-700 flex flex-col shadow-xl overflow-hidden relative backdrop-blur">
            <!-- Messages List -->
            <div class="flex-grow overflow-y-auto p-6 space-y-4 bg-slate-900/30 scroll-smooth custom-scrollbar" id="chatBox">
                <?php if(empty($messages)): ?>
                    <div class="flex flex-col items-center justify-center h-full text-slate-600">
                        <i class="fas fa-robot text-4xl mb-3 opacity-30 text-[#00f0ff]"></i>
                        <p class="text-sm font-medium">Secure Channel Open</p>
                        <p class="text-xs">End-to-End Encrypted interface.</p>
                    </div>
                <?php endif; ?>

                <?php foreach($messages as $msg): ?>
                    <div class="flex <?php echo $msg['sender_type'] == 'admin' ? 'justify-end' : 'justify-start'; ?>">
                        <div class="max-w-[85%] flex flex-col <?php echo $msg['sender_type'] == 'admin' ? 'items-end' : 'items-start'; ?>">
                            
                            <div class="p-3 rounded-2xl text-sm shadow-sm relative <?php echo $msg['sender_type'] == 'admin' ? 'bg-blue-600 text-white rounded-br-none border border-blue-500' : 'bg-slate-700 text-slate-200 rounded-bl-none border border-slate-600'; ?>">
                                
                                <?php if($msg['is_credential']): ?>
                                    <div class="flex items-center gap-2 text-xs font-bold text-[#00f0ff] mb-2 border-b border-white/20 pb-1">
                                        <i class="fas fa-shield-alt"></i> SECURE CREDENTIAL
                                    </div>
                                    <div class="font-mono text-xs whitespace-pre-wrap select-all bg-black/40 p-2 rounded border border-white/10 text-green-300"><?php echo htmlspecialchars($msg['message']); ?></div>
                                <?php else: ?>
                                    <div class="whitespace-pre-wrap leading-relaxed"><?php echo htmlspecialchars($msg['message']); ?></div>
                                <?php endif; ?>
                                
                            </div>
                            
                            <span class="text-[10px] text-slate-500 mt-1 px-1 opacity-70">
                                <?php echo date('H:i', strtotime($msg['created_at'])); ?> • <?php echo $msg['sender_type'] == 'admin' ? 'Agent' : 'Client'; ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Input Area with Smart Tools -->
            <form method="POST" class="border-t border-slate-700 bg-slate-900 shrink-0 z-10 flex flex-col">
                
                <!-- Quick Replies (One-Click) -->
                <div class="px-4 pt-3 flex gap-2 overflow-x-auto no-scrollbar bg-slate-800/50">
                    <?php foreach($quick_replies as $qr): ?>
                        <button type="button" onclick="insertQuickReply('<?php echo addslashes($qr); ?>')" class="whitespace-nowrap bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white border border-slate-700 hover:border-[#00f0ff]/50 rounded-full px-3 py-1 text-[10px] font-medium transition-all shrink-0">
                            <?php echo htmlspecialchars((strlen($qr) > 30 ? substr($qr, 0, 30).'...' : $qr)); ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="p-4 relative">
                    <!-- AI Enhance Tool (UI Flair) -->
                    <button type="button" onclick="enhanceText()" title="Auto-Format Text" class="absolute left-6 top-7 text-purple-400 hover:text-[#00f0ff] transition z-20">
                        <i class="fas fa-magic text-sm"></i>
                    </button>

                    <textarea id="chatInput" name="message" rows="1" placeholder="Type message..." class="w-full bg-slate-800 border border-slate-700 rounded-xl py-3.5 pl-10 pr-32 text-sm text-white focus:border-[#00f0ff] focus:ring-1 focus:ring-[#00f0ff] outline-none resize-none custom-scrollbar shadow-inner transition-all" style="min-height: 52px; max-height: 120px;" oninput="this.style.height = ''; this.style.height = this.scrollHeight + 'px'"></textarea>
                    
                    <div class="absolute right-6 top-6 flex items-center gap-3">
                        <label class="cursor-pointer text-slate-500 hover:text-[#00f0ff] transition flex items-center gap-1 group" title="Send as Secure Credential">
                            <input type="checkbox" id="secureToggle" name="is_credential" value="1" class="hidden peer">
                            <i class="fas fa-lock peer-checked:text-[#00f0ff] peer-checked:animate-pulse"></i>
                            <span class="text-[10px] uppercase font-bold peer-checked:text-[#00f0ff] opacity-0 group-hover:opacity-100 transition-opacity hidden md:inline">Secure</span>
                        </label>
                        <button type="submit" class="bg-gradient-to-r from-blue-600 to-[#00f0ff] hover:from-blue-500 hover:to-[#00f0ff] text-slate-900 w-8 h-8 rounded-lg flex items-center justify-center shadow-[0_0_10px_rgba(0,240,255,0.3)] transition transform active:scale-95">
                            <i class="fas fa-paper-plane text-xs"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Lightbox Element -->
<div id="proofLightbox" class="lightbox" onclick="closeLightbox()">
    <img id="lightboxImg" class="lightbox-content transform scale-95 transition duration-300">
</div>

<script>
    // 1. Auto-scroll chat
    const chatBox = document.getElementById('chatBox');
    const chatInput = document.getElementById('chatInput');
    const secureToggle = document.getElementById('secureToggle');

    if(chatBox) {
        chatBox.scrollTop = chatBox.scrollHeight;
        window.addEventListener('resize', () => chatBox.scrollTop = chatBox.scrollHeight);
    }

    // 2. S-Tier Quick Reply Inserter
    function insertQuickReply(text) {
        chatInput.value = text;
        chatInput.focus();
        chatInput.style.height = '';
        chatInput.style.height = chatInput.scrollHeight + 'px';
        secureToggle.checked = false; // Normal text isn't secure
    }

    // 3. S-Tier Auto-Paste Key Logic
    function autoPasteKey(keyText) {
        chatInput.value = keyText;
        chatInput.focus();
        chatInput.style.height = '';
        chatInput.style.height = chatInput.scrollHeight + 'px';
        secureToggle.checked = true; // Auto-secure sensitive data
        
        // Optional: Provide visual feedback
        chatInput.classList.add('border-[#00f0ff]', 'bg-blue-900/20');
        setTimeout(() => {
            chatInput.classList.remove('border-[#00f0ff]', 'bg-blue-900/20');
        }, 500);
    }

    // 4. Magic Enhance (UI/UX Flair simulating AI grammar cleanup)
    function enhanceText() {
        let val = chatInput.value.trim();
        if(val.length > 2) {
            // Basic capitalization and sign-off
            val = val.charAt(0).toUpperCase() + val.slice(1);
            if(!val.endsWith('.') && !val.endsWith('!') && !val.endsWith('?')) val += '.';
            if(!val.includes('Thank')) val += '\n\nLet me know if you need anything else!';
            
            chatInput.value = val;
            chatInput.style.height = '';
            chatInput.style.height = chatInput.scrollHeight + 'px';
            
            // Visual pop
            chatInput.classList.add('ring-2', 'ring-purple-500');
            setTimeout(() => chatInput.classList.remove('ring-2', 'ring-purple-500'), 400);
        }
    }

    // 5. Lightbox Logic
    function openLightbox(src) {
        const lightbox = document.getElementById('proofLightbox');
        const img = document.getElementById('lightboxImg');
        img.src = src;
        lightbox.style.display = 'flex';
        setTimeout(() => img.classList.remove('scale-95'), 10);
        document.body.style.overflow = "hidden";
    }
    
    function closeLightbox() {
        const lightbox = document.getElementById('proofLightbox');
        const img = document.getElementById('lightboxImg');
        img.classList.add('scale-95');
        setTimeout(() => {
            lightbox.style.display = 'none';
        }, 200);
        document.body.style.overflow = "auto";
    }
</script>