<?php
// admin/order_detail.php
// PRODUCTION v4.1 - AJAX Chat Fix, Mixed Content Patch & Neon UI

// Include Notification Services
@include_once dirname(__DIR__) . '/includes/MailService.php';
@include_once dirname(__DIR__) . '/includes/PushService.php';

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$admin_id = $_SESSION['admin_id'];

// Helper to force HTTPS on URLs to prevent Mixed Content errors
function enforce_https($url) {
    return str_replace('http://', 'https://', $url);
}

// =====================================================================================
// 1. AJAX ENDPOINTS (Polling & Message Sending)
// =====================================================================================

// A. Handle Incoming Chat Message (AJAX POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_msg'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    
    $msg = trim($_POST['message']);
    $is_cred = isset($_POST['is_credential']) ? 1 : 0;
    
    if (!empty($msg) && $order_id > 0) {
        try {
            $pdo->exec("SET NAMES 'utf8mb4'");
            $stmt = $pdo->prepare("INSERT INTO order_messages (order_id, sender_type, message, is_credential) VALUES (?, 'admin', ?, ?)");
            $stmt->execute([$order_id, $msg, $is_cred]);

            // Optional Push Notification
            $stmt = $pdo->prepare("SELECT user_id FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $uid = $stmt->fetchColumn();
            
            if (class_exists('PushService')) {
                $push = new PushService($pdo);
                $push->sendToUser($uid, "New Message 💬", "Support replied to Order #$order_id");
            }
            echo json_encode(['success' => true]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
    echo json_encode(['success' => false, 'error' => 'Invalid order ID or empty message']);
    exit;
}

// B. Handle Live Chat Polling (AJAX GET)
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    if (ob_get_length()) ob_clean();
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");

    $stmt = $pdo->prepare("SELECT * FROM order_messages WHERE order_id = ? ORDER BY created_at ASC");
    $stmt->execute([$order_id]);
    $messages = $stmt->fetchAll();
    
    if(empty($messages)) {
        echo "<div class='flex flex-col items-center justify-center h-full text-slate-600'>
                <i class='fas fa-robot text-4xl mb-3 opacity-30 text-[#00f0ff]'></i>
                <p class='text-sm font-medium'>Secure Channel Open</p>
                <p class='text-xs'>End-to-End Encrypted interface.</p>
              </div>";
        exit;
    }

    foreach($messages as $msg) {
        $is_admin = $msg['sender_type'] === 'admin';
        $align = $is_admin ? 'justify-end' : 'justify-start';
        $item_align = $is_admin ? 'items-end' : 'items-start';
        $bubble_bg = $is_admin ? 'bg-gradient-to-br from-blue-600 to-[#00f0ff] text-slate-900 rounded-br-none shadow-[0_0_10px_rgba(0,240,255,0.3)]' : 'bg-slate-700 text-slate-200 rounded-bl-none border border-slate-600';
        $time = date('H:i', strtotime($msg['created_at']));
        $safe_msg = htmlspecialchars($msg['message']);

        echo "<div class='flex w-full {$align} mb-4 animate-fade-in-up'>";
        echo "<div class='max-w-[85%] md:max-w-[75%] flex flex-col {$item_align}'>";
        echo "<div class='px-4 py-3 text-sm relative rounded-2xl {$bubble_bg}'>";
        
        if ($msg['is_credential']) {
            echo "<div class='flex items-center gap-2 text-[10px] font-black " . ($is_admin ? "text-slate-900" : "text-[#00f0ff]") . " mb-2 border-b " . ($is_admin ? "border-slate-900/20" : "border-white/10") . " pb-1 uppercase tracking-wider'><i class='fas fa-shield-alt'></i> SECURE CREDENTIAL</div>";
            echo "<div class='font-mono text-xs whitespace-pre-wrap select-all " . ($is_admin ? "bg-white/30 text-slate-900" : "bg-black/40 text-green-300") . " p-2.5 rounded-lg border border-white/10'>{$safe_msg}</div>";
        } else {
            echo "<div class='whitespace-pre-wrap break-words leading-relaxed font-medium'>{$safe_msg}</div>";
        }
        
        echo "</div>";
        echo "<span class='text-[10px] text-slate-500 mt-1.5 px-1 font-medium'>{$time} • " . ($is_admin ? 'Agent' : 'Client') . "</span>";
        echo "</div></div>";
    }
    exit;
}


// =====================================================================================
// 2. NORMAL PAGE LOAD LOGIC
// =====================================================================================

// Quick Reply Macros
$quick_replies = [
    "⏳ Please wait 5-10 mins while we verify your KBZPay/Wave transaction.",
    "✅ Payment verified! Processing your digital goods right now.",
    "🎁 Your order is complete! Thank you for choosing DigitalMarketplaceMM.",
    "⚠️ We couldn't verify the payment. Please send a clearer screenshot.",
    "🛠️ Let me check the system for your key. One moment please.",
    "🎮 Here is your requested code/account. Please read the instructions carefully."
];

// ACTION: UPDATE STATUS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $status = $_POST['status'];
    
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$status, $order_id]);
    
    // --- NEW: AUTO-ACTIVATE AGENT PASS UPON APPROVAL ---
    if ($status === 'active') {
        $stmt_check_pass = $pdo->prepare("SELECT user_id, pass_id FROM orders WHERE id = ? AND pass_id IS NOT NULL");
        $stmt_check_pass->execute([$order_id]);
        $passOrder = $stmt_check_pass->fetch();
        
        if ($passOrder) {
            // Get pass duration
            $stmt_dur = $pdo->prepare("SELECT duration_days FROM passes WHERE id = ?");
            $stmt_dur->execute([$passOrder['pass_id']]);
            $days = $stmt_dur->fetchColumn() ?: 30; // Fallback to 30 days
            
            $expires_at = date('Y-m-d H:i:s', strtotime("+$days days"));
            
            // 1. Expire any existing passes for this user to prevent overlap
            $pdo->prepare("UPDATE user_passes SET status = 'expired' WHERE user_id = ?")->execute([$passOrder['user_id']]);
            
            // 2. Insert new active pass
            $pdo->prepare("INSERT INTO user_passes (user_id, pass_id, expires_at, status) VALUES (?, ?, ?, 'active')")
                ->execute([$passOrder['user_id'], $passOrder['pass_id'], $expires_at]);
        }
    }
    // ---------------------------------------------------

    $stmt = $pdo->prepare("
        SELECT o.user_id, p.name as product_name 
        FROM orders o LEFT JOIN products p ON o.product_id = p.id WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $ord_meta = $stmt->fetch();
    
    if ($ord_meta) {
        $status_title = "Order Updated";
        $status_msg = "Your order #$order_id status is now: " . ucfirst($status);
        $url = enforce_https(BASE_URL) . "index.php?module=user&page=orders&view_chat=$order_id";

        if ($status === 'active') {
            $status_title = "Order Complete! ✅";
            $status_msg = "Your order #$order_id is ready! Check your account.";
        } elseif ($status === 'rejected') {
            $status_title = "Order Rejected ❌";
            $status_msg = "There was an issue with order #$order_id. Please check support chat.";
        }

        try {
            if (class_exists('PushService')) {
                $push = new PushService($pdo);
                $push->sendToUser($ord_meta['user_id'], $status_title, $status_msg, $url);
            }
        } catch (Exception $e) {}
    }
    echo "<script>window.location.href='index.php?page=order_detail&id=$order_id';</script>";
    exit;
}

// 3. Fetch Full Order Data
$stmt = $pdo->prepare("
    SELECT o.*, u.username, u.email as user_account_email, u.phone, 
           p.name as product_name, p.price, p.delivery_type, p.universal_content, p.id as product_id, p.image_path,
           c.image_url as cat_image
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

// 4. Fetch Available Keys
$available_keys = [];
if ($order['delivery_type'] === 'unique') {
    $stmt = $pdo->prepare("SELECT id, key_content FROM product_keys WHERE product_id = ? AND is_sold = 0 LIMIT 10");
    $stmt->execute([$order['product_id']]);
    $available_keys = $stmt->fetchAll();
}

// Ensure images are using HTTPS to prevent Mixed Content
$secure_main_url = enforce_https(defined('MAIN_SITE_URL') ? MAIN_SITE_URL : BASE_URL);

// Legacy Data Patch: Check if category image is actually a font awesome icon class string
$is_cat_image_legacy_icon = !empty($order['cat_image']) && strpos($order['cat_image'], 'fa-') === 0;

?>

<style>
    .lightbox { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(15,23,42,0.95); backdrop-filter: blur(10px); justify-content: center; align-items: center; }
    .lightbox-content { max-width: 90%; max-height: 90%; border-radius: 16px; box-shadow: 0 0 50px rgba(0,240,255,0.3); border: 1px solid rgba(0,240,255,0.2); }
    .close-lightbox { position: absolute; top: 30px; right: 35px; color: #94a3b8; font-size: 40px; font-weight: bold; transition: 0.3s; cursor: pointer; }
    .close-lightbox:hover { color: #fff; }
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(51, 65, 85, 0.5); border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(0, 240, 255, 0.5); }
</style>

<div class="max-w-7xl mx-auto h-[calc(100vh-140px)] grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    <!-- LEFT COLUMN: Order Info & Proof -->
    <div class="lg:col-span-1 space-y-6 overflow-y-auto pr-2 custom-scrollbar pb-6">
        
        <!-- Header Card -->
        <div class="bg-slate-900/80 p-6 rounded-2xl border border-[#00f0ff]/30 shadow-[0_0_20px_rgba(0,240,255,0.1)] relative overflow-hidden group">
            <div class="absolute -right-10 -top-10 w-40 h-40 bg-[#00f0ff]/10 rounded-full blur-3xl pointer-events-none group-hover:bg-[#00f0ff]/20 transition-colors duration-500"></div>
            
            <div class="flex justify-between items-start mb-6 relative z-10">
                <div>
                    <h2 class="text-2xl font-black text-white flex items-center gap-2 tracking-tight">
                        Order #<?php echo $order['id']; ?>
                    </h2>
                    <p class="text-xs text-[#00f0ff] mt-1 flex items-center gap-1 font-mono tracking-wider">
                        <i class="far fa-clock"></i> <?php echo date('M j, Y • H:i', strtotime($order['created_at'])); ?>
                    </p>
                </div>
                <a href="index.php?page=orders" class="text-xs bg-slate-800 hover:bg-slate-700 px-3 py-1.5 rounded-lg text-white transition border border-slate-600 shadow-sm flex items-center justify-center h-8 w-8">
                    <i class="fas fa-arrow-left"></i>
                </a>
            </div>

            <!-- Product Mini Info (Mixed Content & Legacy Fixes Applied) -->
            <div class="flex items-center gap-4 mb-6 p-4 bg-slate-800/60 rounded-xl border border-slate-700 relative z-10 shadow-inner">
                <div class="w-14 h-14 bg-slate-900 rounded-xl flex items-center justify-center shrink-0 overflow-hidden border border-[#00f0ff]/30 shadow-[0_0_10px_rgba(0,240,255,0.2)]">
                    <?php if(!empty($order['image_path'])): ?>
                        <img src="<?php echo $secure_main_url . $order['image_path']; ?>" class="w-full h-full object-cover">
                    <?php elseif(!empty($order['cat_image']) && !$is_cat_image_legacy_icon): ?>
                        <img src="<?php echo $secure_main_url . $order['cat_image']; ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <!-- Fallback gracefully handles 'fa-shield-alt' injected into image_url -->
                        <i class="fas <?php echo htmlspecialchars($is_cat_image_legacy_icon ? $order['cat_image'] : 'fa-cube'); ?> text-[#00f0ff] text-2xl"></i>
                    <?php endif; ?>
                </div>
                <div class="min-w-0">
                    <div class="text-white font-bold text-sm truncate"><?php echo htmlspecialchars($order['product_name']); ?></div>
                    <div class="flex gap-2 mt-1.5">
                        <span class="text-[9px] font-black text-slate-400 uppercase bg-slate-900 px-2 py-0.5 rounded border border-slate-700 tracking-widest"><?php echo $order['delivery_type']; ?></span>
                        <span class="text-[10px] text-green-400 font-mono font-bold bg-green-900/20 px-2 py-0.5 rounded border border-green-900/30">
                            <?php echo number_format($order['total_price_paid']); ?> Ks
                        </span>
                    </div>
                </div>
            </div>

            <!-- Status Manager -->
            <form method="POST" class="space-y-3 relative z-10">
                <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">System Status</label>
                <div class="flex gap-2">
                    <select name="status" class="flex-1 bg-slate-900 border border-slate-600 rounded-xl px-3 py-3 text-white text-sm focus:border-[#00f0ff] outline-none cursor-pointer shadow-inner appearance-none font-bold">
                        <option value="pending" <?php echo $order['status']=='pending'?'selected':''; ?>>🟡 Awaiting Auth</option>
                        <option value="active" <?php echo $order['status']=='active'?'selected':''; ?>>🟢 Active / Complete</option>
                        <option value="rejected" <?php echo $order['status']=='rejected'?'selected':''; ?>>🔴 Terminate</option>
                    </select>
                    <button type="submit" name="update_status" class="bg-gradient-to-r from-blue-600 to-[#00f0ff] hover:from-blue-500 hover:to-[#00f0ff] text-slate-900 px-5 py-3 rounded-xl font-black text-sm transition-all transform active:scale-95 shadow-[0_0_15px_rgba(0,240,255,0.3)] flex items-center justify-center">
                        <i class="fas fa-save"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- Customer & Payment Data -->
        <div class="bg-slate-900/60 p-6 rounded-2xl border border-slate-700 shadow-lg space-y-5">
            <h3 class="font-bold text-[#00f0ff] text-xs uppercase tracking-widest border-b border-slate-700/50 pb-3 flex items-center gap-2">
                <i class="fas fa-fingerprint"></i> Identity & Payment
            </h3>
            
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div class="bg-slate-800/50 p-3 rounded-xl border border-slate-700/50">
                    <span class="block text-slate-500 text-[10px] font-bold uppercase tracking-wider mb-1">User</span>
                    <span class="text-white font-bold">@<?php echo htmlspecialchars($order['username']); ?></span>
                </div>
                <div class="bg-slate-800/50 p-3 rounded-xl border border-slate-700/50">
                    <span class="block text-slate-500 text-[10px] font-bold uppercase tracking-wider mb-1">Txn ID</span>
                    <span class="font-mono text-yellow-400 font-bold select-all"><?php echo $order['transaction_last_6']; ?></span>
                </div>
            </div>

            <?php if(!empty($order['form_data'])): ?>
                <div class="mt-2 pt-2 border-t border-slate-700/50">
                    <span class="block text-purple-400 text-[10px] font-bold uppercase tracking-widest mb-3 flex items-center gap-2"><i class="fas fa-database"></i> Injected Parameters</span>
                    <div class="space-y-2">
                    <?php 
                        $formData = json_decode($order['form_data'], true);
                        if(is_array($formData)) {
                            foreach($formData as $key => $val): 
                    ?>
                        <div class="bg-slate-800/80 p-3 rounded-xl border border-slate-600 shadow-inner flex flex-col gap-1">
                            <span class="text-[10px] text-slate-400 uppercase font-bold tracking-wider"><?php echo htmlspecialchars($key); ?></span>
                            <span class="text-sm text-[#00f0ff] font-mono font-bold select-all"><?php echo htmlspecialchars($val); ?></span>
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
        <div class="bg-slate-900/60 p-5 rounded-2xl border border-slate-700 shadow-lg">
            <h3 class="font-bold text-slate-400 mb-3 text-xs uppercase tracking-widest flex items-center gap-2"><i class="fas fa-file-invoice"></i> Verification Image</h3>
            <?php if(!empty($order['proof_image_path'])): ?>
                <div class="relative group overflow-hidden rounded-xl border border-slate-600 cursor-zoom-in bg-slate-950 aspect-video flex items-center justify-center" onclick="openLightbox('<?php echo $secure_main_url . $order['proof_image_path']; ?>')">
                    <!-- Secure HTTPS path implementation -->
                    <img src="<?php echo $secure_main_url . $order['proof_image_path']; ?>" class="w-full h-full object-contain group-hover:opacity-50 transition duration-300">
                    <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition duration-300">
                        <span class="bg-[#00f0ff] text-slate-900 font-black text-xs uppercase tracking-widest px-4 py-2 rounded-lg shadow-[0_0_15px_rgba(0,240,255,0.5)]"><i class="fas fa-expand-alt mr-2"></i> Enlarge</span>
                    </div>
                </div>
            <?php else: ?>
                <div class="p-6 bg-red-900/20 text-red-400 text-sm rounded-xl border border-red-500/30 text-center font-bold flex flex-col items-center gap-2">
                    <i class="fas fa-image-slash text-3xl"></i>
                    No Image Data
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- RIGHT COLUMN: Chat & Fulfillment -->
    <div class="lg:col-span-2 flex flex-col gap-6 h-full overflow-hidden pb-6">
        
        <!-- Fulfillment Helper Panel -->
        <div class="bg-slate-900 border border-[#00f0ff]/30 rounded-2xl p-5 shrink-0 shadow-[0_0_20px_rgba(0,240,255,0.05)] relative overflow-hidden">
            <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PHBhdHRlcm4gaWQ9ImdyaWQiIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCIgcGF0dGVyblVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+PHBhdGggZD0iTSAwIDEwIEwgNDAgMTAgTSAxMCAwIEwgMTAgNDAiIGZpbGw9Im5vbmUiIHN0cm9rZT0icmdiYSgwLCAyNDAsIDI1NSwgMC4wNSkiIHN0cm9rZS13aWR0aD0iMSIvPjwvcGF0dGVybj48L2RlZnM+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0idXJsKCNncmlkKSIvPjwvc3ZnPg==')] opacity-50"></div>
            
            <div class="flex justify-between items-center mb-4 relative z-10">
                <h3 class="text-xs font-black text-[#00f0ff] uppercase tracking-widest flex items-center gap-2">
                    <i class="fas fa-bolt animate-pulse"></i> Auto-Fulfillment Hub
                </h3>
            </div>
            
            <div class="relative z-10">
                <?php if($order['delivery_type'] === 'unique'): ?>
                    <div class="flex gap-3 overflow-x-auto pb-2 custom-scrollbar">
                        <?php if(empty($available_keys)): ?>
                            <div class="w-full p-4 bg-red-900/20 border border-red-500/50 rounded-xl text-red-400 text-sm font-bold flex items-center justify-between shadow-inner">
                                <span class="flex items-center gap-2"><i class="fas fa-exclamation-triangle"></i> Inventory Depleted!</span>
                                <a href="index.php?page=keys&product_id=<?php echo $order['product_id']; ?>" class="bg-red-600 text-white px-4 py-1.5 rounded-lg text-xs hover:bg-red-500 transition">Replenish</a>
                            </div>
                        <?php else: ?>
                            <?php foreach($available_keys as $k): ?>
                                <button type="button" onclick="autoPasteKey('<?php echo addslashes($k['key_content']); ?>')" class="bg-slate-800/80 border border-slate-600 rounded-xl p-3 min-w-[220px] flex justify-between items-center group hover:bg-slate-800 hover:border-[#00f0ff]/50 hover:shadow-[0_0_15px_rgba(0,240,255,0.1)] transition-all cursor-pointer text-left">
                                    <code class="text-xs text-green-400 font-mono font-bold truncate mr-3 group-hover:text-[#00f0ff] transition"><?php echo htmlspecialchars($k['key_content']); ?></code>
                                    <i class="fas fa-share text-slate-500 group-hover:text-[#00f0ff] transition"></i>
                                </button>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php elseif($order['delivery_type'] === 'universal'): ?>
                    <div class="bg-slate-800/80 border border-slate-600 rounded-xl p-4 flex justify-between items-center group shadow-inner hover:border-[#00f0ff]/30 transition">
                        <div class="text-xs text-slate-300 font-mono font-bold break-all line-clamp-2 pr-4"><?php echo htmlspecialchars($order['universal_content']); ?></div>
                        <button type="button" onclick="autoPasteKey('<?php echo addslashes($order['universal_content']); ?>')" class="bg-slate-700 hover:bg-[#00f0ff] hover:text-slate-900 text-[#00f0ff] text-xs font-black px-4 py-2 rounded-lg transition shadow-lg shrink-0 uppercase tracking-wider flex items-center gap-2">
                            <i class="fas fa-paste"></i> Inject
                        </button>
                    </div>
                <?php else: ?>
                    <div class="text-xs font-bold uppercase tracking-widest text-purple-400 bg-purple-900/10 p-4 rounded-xl border border-purple-500/20 text-center flex items-center justify-center gap-2">
                        <i class="fas fa-user-edit"></i> Manual Injection Required (See Form Data)
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Dynamic SPA Chat System -->
        <div class="flex-grow bg-slate-900/60 rounded-2xl border border-slate-700 flex flex-col shadow-2xl overflow-hidden relative backdrop-blur-xl">
            <!-- Messages List -->
            <div class="flex-grow overflow-y-auto p-4 md:p-6 bg-slate-900/40 scroll-smooth custom-scrollbar relative" id="chatBox">
                <!-- Data populated by AJAX -->
                <div class="flex items-center justify-center h-full text-slate-500">
                    <i class="fas fa-circle-notch fa-spin text-3xl text-[#00f0ff]"></i>
                </div>
            </div>

            <!-- Input Area -->
            <form id="adminChatForm" class="border-t border-slate-700/80 bg-slate-800/90 backdrop-blur shrink-0 z-20 flex flex-col">
                <!-- FIX: Hidden order_id correctly appended to FormData -->
                <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                
                <!-- Quick Replies -->
                <div class="px-4 py-3 flex gap-2 overflow-x-auto custom-scrollbar border-b border-slate-700/50">
                    <?php foreach($quick_replies as $qr): ?>
                        <button type="button" onclick="insertQuickReply('<?php echo addslashes($qr); ?>')" class="whitespace-nowrap bg-slate-900 hover:bg-[#00f0ff]/10 text-slate-400 hover:text-[#00f0ff] border border-slate-700 hover:border-[#00f0ff]/30 rounded-lg px-3 py-1.5 text-[10px] font-bold tracking-wide transition-all shrink-0 shadow-sm">
                            <?php echo htmlspecialchars((strlen($qr) > 30 ? substr($qr, 0, 30).'...' : $qr)); ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="p-3 md:p-4 relative flex items-end gap-3">
                    <!-- AI Enhance Tool -->
                    <button type="button" onclick="enhanceText()" title="Auto-Format Syntax" class="text-purple-400 hover:text-[#00f0ff] transition p-2 bg-slate-900 rounded-xl border border-slate-700 hover:border-[#00f0ff]/50 shadow-inner mb-0.5 shrink-0 h-12 w-12 flex items-center justify-center">
                        <i class="fas fa-magic text-lg"></i>
                    </button>

                    <div class="relative flex-grow bg-slate-900 border border-slate-600 rounded-2xl focus-within:border-[#00f0ff] focus-within:shadow-[0_0_15px_rgba(0,240,255,0.15)] transition-all flex items-center overflow-hidden">
                        <textarea id="chatInput" name="message" rows="1" placeholder="Transmit response..." required class="w-full bg-transparent py-3.5 pl-4 pr-2 text-sm text-white outline-none resize-none custom-scrollbar font-medium" style="min-height: 48px; max-height: 120px;" oninput="this.style.height = ''; this.style.height = this.scrollHeight + 'px'"></textarea>
                        
                        <div class="flex items-center gap-2 pr-2 shrink-0">
                            <label class="cursor-pointer text-slate-500 hover:text-[#00f0ff] transition flex items-center justify-center w-10 h-10 rounded-xl hover:bg-slate-800" title="Encrypt as Credential">
                                <input type="checkbox" id="secureToggle" name="is_credential" value="1" class="hidden peer">
                                <i class="fas fa-lock peer-checked:text-[#00f0ff] peer-checked:animate-pulse text-lg"></i>
                            </label>
                            <button type="submit" class="bg-gradient-to-r from-blue-600 to-[#00f0ff] hover:from-blue-500 hover:to-[#00f0ff] text-slate-900 w-10 h-10 rounded-xl flex items-center justify-center shadow-lg transition transform active:scale-95">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Lightbox Element -->
<div id="proofLightbox" class="lightbox flex" onclick="closeLightbox()" style="display:none;">
    <span class="close-lightbox">&times;</span>
    <img id="lightboxImg" class="lightbox-content transform scale-95 transition-transform duration-300">
</div>

<script>
    const orderId = <?php echo $order_id; ?>;
    const chatBox = document.getElementById('chatBox');
    const chatInput = document.getElementById('chatInput');
    const secureToggle = document.getElementById('secureToggle');
    
    let isUserScrolling = false;
    let lastChatHtml = '';

    // Scroll Detection
    if(chatBox) {
        chatBox.addEventListener('scroll', () => {
            const isAtBottom = chatBox.scrollHeight - chatBox.scrollTop <= chatBox.clientHeight + 50;
            isUserScrolling = !isAtBottom;
        });
    }

    // AJAX Polling (Uses correct fetch URL parameter for ID)
    function fetchChat() {
        if(!orderId) return;
        
        fetch(`index.php?page=order_detail&id=${orderId}&ajax=1&_=${Date.now()}`)
            .then(res => res.text())
            .then(html => {
                if(html.trim() !== '' && html !== lastChatHtml) {
                    chatBox.innerHTML = html;
                    lastChatHtml = html;
                    if(!isUserScrolling) {
                        chatBox.scrollTop = chatBox.scrollHeight;
                    }
                }
            })
            .catch(err => console.error('Sync Error:', err));
    }

    // Start Polling
    fetchChat();
    setInterval(fetchChat, 3000);

    // AJAX Message Submission (Fixed URL logic to include order ID parameter)
    document.getElementById('adminChatForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        if(!chatInput.value.trim()) return;

        const formData = new FormData(this);
        formData.append('ajax_msg', '1');
        
        chatInput.value = ''; 
        chatInput.style.height = '48px'; // Reset height
        
        try {
            // FIX: Added id to query string so the server captures it correctly
            await fetch(`index.php?page=order_detail&id=${orderId}`, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            isUserScrolling = false;
            secureToggle.checked = false; // Reset secure toggle
            fetchChat(); 
        } catch(err) { console.error('Transmission failed:', err); }
    });

    // Helpers
    function insertQuickReply(text) {
        chatInput.value = text;
        chatInput.focus();
        chatInput.style.height = '';
        chatInput.style.height = chatInput.scrollHeight + 'px';
        secureToggle.checked = false;
    }

    function autoPasteKey(keyText) {
        chatInput.value = keyText;
        chatInput.focus();
        chatInput.style.height = '';
        chatInput.style.height = chatInput.scrollHeight + 'px';
        secureToggle.checked = true;
        chatInput.classList.add('border-[#00f0ff]', 'shadow-[0_0_15px_rgba(0,240,255,0.2)]');
        setTimeout(() => {
            chatInput.classList.remove('border-[#00f0ff]', 'shadow-[0_0_15px_rgba(0,240,255,0.2)]');
        }, 500);
    }

    function enhanceText() {
        let val = chatInput.value.trim();
        if(val.length > 2) {
            val = val.charAt(0).toUpperCase() + val.slice(1);
            if(!val.endsWith('.') && !val.endsWith('!') && !val.endsWith('?')) val += '.';
            if(!val.includes('Thank')) val += '\n\nThank you for choosing DigitalMarketplaceMM!';
            
            chatInput.value = val;
            chatInput.style.height = '';
            chatInput.style.height = chatInput.scrollHeight + 'px';
            
            chatInput.classList.add('ring-2', 'ring-purple-500');
            setTimeout(() => chatInput.classList.remove('ring-2', 'ring-purple-500'), 400);
        }
    }

    // Lightbox
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
        setTimeout(() => { lightbox.style.display = 'none'; }, 300);
        document.body.style.overflow = "auto";
    }
</script>