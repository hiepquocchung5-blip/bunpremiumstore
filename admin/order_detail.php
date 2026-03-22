<?php
// admin/order_detail.php
// PRODUCTION v5.2 - Patched AJAX Output Bleed & Isolated Comms Terminal

// Include Notification Services
@include_once dirname(__DIR__) . '/includes/MailService.php';
@include_once dirname(__DIR__) . '/includes/PushService.php';

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$admin_id = $_SESSION['admin_id'];

// Helper to force HTTPS on URLs to prevent Mixed Content errors
function enforce_https($url) {
    if (empty($url)) return $url;
    return str_replace('http://', 'https://', $url);
}

// =====================================================================================
// 1. AJAX ENDPOINTS (Polling & Message Sending - Standard Orders Only)
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

    // FIX: Add strict payload delimiters so JS can extract ONLY the chat content,
    // ignoring any header/sidebar HTML accidentally output by the admin/index.php wrapper.
    echo "<!--CHAT_PAYLOAD_START-->";

    $stmt = $pdo->prepare("SELECT * FROM order_messages WHERE order_id = ? ORDER BY created_at ASC");
    $stmt->execute([$order_id]);
    $messages = $stmt->fetchAll();
    
    if(empty($messages)) {
        echo "<div class='flex flex-col items-center justify-center h-full text-slate-500'>
                <div class='w-20 h-20 bg-[#00f0ff]/10 rounded-full flex items-center justify-center mb-4 shadow-[0_0_30px_rgba(0,240,255,0.1)]'>
                    <i class='fas fa-satellite-dish text-3xl opacity-50 text-[#00f0ff] animate-pulse'></i>
                </div>
                <p class='text-sm font-bold text-[#00f0ff] tracking-widest uppercase'>Secure Channel Open</p>
                <p class='text-xs mt-1'>Awaiting communication initialization.</p>
              </div>";
    } else {
        foreach($messages as $msg) {
            $is_admin = $msg['sender_type'] === 'admin';
            $align = $is_admin ? 'justify-end' : 'justify-start';
            $item_align = $is_admin ? 'items-end' : 'items-start';
            
            // Messenger style bubble tails
            $bubble_bg = $is_admin 
                ? 'bg-gradient-to-br from-blue-600 to-[#00f0ff] text-slate-900 rounded-2xl rounded-br-sm shadow-[0_4px_15px_rgba(0,240,255,0.2)]' 
                : 'bg-slate-800 text-slate-200 border border-slate-700 rounded-2xl rounded-bl-sm shadow-md';
                
            $time = date('H:i', strtotime($msg['created_at']));
            $safe_msg = htmlspecialchars($msg['message']);

            echo "<div class='flex w-full {$align} mb-4 animate-fade-in-up group'>";
            echo "<div class='max-w-[85%] sm:max-w-[75%] flex flex-col {$item_align}'>";
            echo "<div class='px-4 py-3 text-[13px] md:text-sm relative {$bubble_bg}'>";
            
            if ($msg['is_credential']) {
                echo "<div class='flex items-center gap-2 text-[10px] font-black " . ($is_admin ? "text-slate-900" : "text-[#00f0ff]") . " mb-2 border-b " . ($is_admin ? "border-slate-900/20" : "border-white/10") . " pb-1 uppercase tracking-wider'><i class='fas fa-shield-alt'></i> SECURE CREDENTIAL</div>";
                echo "<div class='font-mono text-xs whitespace-pre-wrap select-all " . ($is_admin ? "bg-white/30 text-slate-900" : "bg-black/40 text-green-300") . " p-2.5 rounded-lg border border-white/10'>{$safe_msg}</div>";
            } else {
                echo "<div class='whitespace-pre-wrap break-words leading-relaxed font-medium'>{$safe_msg}</div>";
            }
            
            echo "</div>";
            echo "<div class='flex items-center gap-1.5 mt-1 px-1 opacity-60 group-hover:opacity-100 transition-opacity'>";
            if($is_admin) echo "<i class='fas fa-check-double text-[8px] text-[#00f0ff]'></i>";
            echo "<span class='text-[9px] text-slate-400 font-medium tracking-wide'>{$time}</span>";
            echo "</div>";
            echo "</div></div>";
        }
    }
    
    echo "<!--CHAT_PAYLOAD_END-->";
    exit;
}


// =====================================================================================
// 2. NORMAL PAGE LOAD LOGIC
// =====================================================================================

$quick_replies = [
    "⏳ Please wait 5-10 mins while we verify your transaction.",
    "✅ Payment verified! Processing your digital goods right now.",
    "🎁 Your order is complete! Thank you for choosing DigitalMarketplaceMM.",
    "⚠️ We couldn't verify the payment. Please send a clearer screenshot.",
    "🛠️ Let me check the system for your key. One moment please."
];

// ACTION: UPDATE STATUS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    
    $status = isset($_POST['status']) ? $_POST['status'] : $_POST['update_status'];
    
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$status, $order_id]);
    
    // --- AUTO-ACTIVATE AGENT PASS UPON APPROVAL ---
    if ($status === 'active') {
        $stmt_check_pass = $pdo->prepare("SELECT user_id, pass_id FROM orders WHERE id = ? AND pass_id IS NOT NULL");
        $stmt_check_pass->execute([$order_id]);
        $passOrder = $stmt_check_pass->fetch();
        
        if ($passOrder) {
            $stmt_dur = $pdo->prepare("SELECT duration_days FROM passes WHERE id = ?");
            $stmt_dur->execute([$passOrder['pass_id']]);
            $days = $stmt_dur->fetchColumn() ?: 30;
            
            $expires_at = date('Y-m-d H:i:s', strtotime("+$days days"));
            
            $pdo->prepare("UPDATE user_passes SET status = 'expired' WHERE user_id = ?")->execute([$passOrder['user_id']]);
            
            $pdo->prepare("INSERT INTO user_passes (user_id, pass_id, expires_at, status) VALUES (?, ?, ?, 'active')")
                ->execute([$passOrder['user_id'], $passOrder['pass_id'], $expires_at]);
        }
    }

    // Notifications
    $stmt = $pdo->prepare("
        SELECT o.user_id, COALESCE(p.name, ps.name) as product_name 
        FROM orders o 
        LEFT JOIN products p ON o.product_id = p.id 
        LEFT JOIN passes ps ON o.pass_id = ps.id
        WHERE o.id = ?
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
           COALESCE(p.name, ps.name) as product_name, 
           COALESCE(p.price, ps.price) as price, 
           COALESCE(p.delivery_type, 'universal') as delivery_type, 
           p.universal_content, p.id as product_id, p.image_path,
           c.image_url as cat_image,
           o.pass_id
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    LEFT JOIN products p ON o.product_id = p.id 
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN passes ps ON o.pass_id = ps.id
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    echo "<div class='p-6 bg-red-500/20 text-red-400 rounded-xl border border-red-500/50 mt-6 mx-4'>Order #$order_id not found.</div>";
    return;
}

$is_pass_order = !empty($order['pass_id']);

// 4. Fetch Available Keys
$available_keys = [];
if (!$is_pass_order && $order['delivery_type'] === 'unique' && !empty($order['product_id'])) {
    $stmt = $pdo->prepare("SELECT id, key_content FROM product_keys WHERE product_id = ? AND is_sold = 0 LIMIT 10");
    $stmt->execute([$order['product_id']]);
    $available_keys = $stmt->fetchAll();
}

$secure_main_url = enforce_https(defined('MAIN_SITE_URL') ? MAIN_SITE_URL : BASE_URL);
$is_cat_image_legacy_icon = !empty($order['cat_image']) && strpos($order['cat_image'], 'fa-') === 0;

?>

<style>
    .lightbox { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: hidden; background-color: rgba(15,23,42,0.95); backdrop-filter: blur(15px); flex-direction: column; justify-content: center; align-items: center; }
    .lightbox-content { max-width: 95vw; max-height: 85vh; border-radius: 12px; box-shadow: 0 0 40px rgba(0,240,255,0.2); object-fit: contain; }
    .close-lightbox { position: absolute; top: 20px; right: 20px; width: 44px; height: 44px; background: rgba(0,0,0,0.5); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; cursor: pointer; border: 1px solid rgba(255,255,255,0.1); }
    
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    
    .custom-scrollbar::-webkit-scrollbar { width: 5px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(51, 65, 85, 0.8); border-radius: 10px; }
    
    /* WhatsApp/Messenger style background for chat */
    .chat-bg-pattern {
        background-color: rgba(15, 23, 42, 0.4);
        background-image: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23334155' fill-opacity='0.15' fill-rule='evenodd'%3E%3Cpath d='M0 40L40 0H20L0 20M40 40V20L20 40'/%3E%3C/g%3E%3C/svg%3E");
    }
</style>

<!-- Mobile Tab Controller (Visible < 1024px) -->
<div class="lg:hidden px-4 pt-4 pb-2 z-30 relative shrink-0">
    <div class="bg-slate-800 p-1 rounded-xl flex shadow-inner border border-slate-700">
        <button id="tabBtnInfo" onclick="switchMobileTab('info')" class="flex-1 py-2.5 text-xs font-bold uppercase tracking-wider rounded-lg transition-all bg-slate-700 text-white shadow-sm">
            <i class="fas fa-database mr-1"></i> Intelligence
        </button>
        <button id="tabBtnChat" onclick="switchMobileTab('chat')" class="flex-1 py-2.5 text-xs font-bold uppercase tracking-wider rounded-lg transition-all text-slate-400 hover:text-white relative">
            <i class="fas fa-comments mr-1"></i> Comms
            <?php if($order['status'] == 'pending'): ?><span class="absolute top-2 right-4 w-2 h-2 rounded-full bg-[#00f0ff] animate-pulse"></span><?php endif; ?>
        </button>
    </div>
</div>

<!-- Main Layout Container (Uses min-h-0 and dvh for perfect mobile scaling without header conflict) -->
<div class="max-w-[1600px] w-full mx-auto flex-1 flex flex-col lg:flex-row gap-6 px-4 pb-6 min-h-0 overflow-hidden h-[calc(100dvh-180px)] lg:h-[calc(100vh-100px)]">
    
    <!-- ========================================== -->
    <!-- LEFT PANEL: Order Info & Verification      -->
    <!-- ========================================== -->
    <div id="colInfo" class="w-full lg:w-1/3 xl:w-1/4 flex flex-col gap-6 overflow-y-auto custom-scrollbar lg:h-full pb-10 lg:pb-0 transition-all shrink-0 lg:shrink">
        
        <!-- Header Card -->
        <div class="bg-slate-900/80 p-5 rounded-2xl border <?php echo $is_pass_order ? 'border-yellow-500/30' : 'border-[#00f0ff]/30'; ?> relative overflow-hidden group shrink-0">
            <div class="absolute -right-10 -top-10 w-32 h-32 <?php echo $is_pass_order ? 'bg-yellow-500/10' : 'bg-[#00f0ff]/10'; ?> rounded-full blur-3xl pointer-events-none group-hover:opacity-100 transition-opacity duration-500"></div>
            
            <div class="flex justify-between items-start mb-4 relative z-10">
                <div>
                    <?php if($is_pass_order): ?>
                        <span class="text-[9px] font-black text-yellow-500 uppercase tracking-widest bg-yellow-500/10 px-2 py-0.5 rounded border border-yellow-500/20 inline-block mb-1.5 shadow-sm">Agent Upgrade Protocol</span>
                    <?php endif; ?>
                    <h2 class="text-xl md:text-2xl font-black text-white flex items-center gap-2 tracking-tight">
                        Order #<?php echo $order['id']; ?>
                    </h2>
                    <p class="text-[10px] <?php echo $is_pass_order ? 'text-yellow-400' : 'text-[#00f0ff]'; ?> mt-0.5 flex items-center gap-1 font-mono tracking-wider">
                        <i class="far fa-clock"></i> <?php echo date('M j, Y • H:i', strtotime($order['created_at'])); ?>
                    </p>
                </div>
                <a href="index.php?page=orders" class="text-xs bg-slate-800 hover:bg-slate-700 rounded-lg text-white transition border border-slate-600 flex items-center justify-center h-10 w-10 shadow-sm shrink-0">
                    <i class="fas fa-arrow-left"></i>
                </a>
            </div>

            <!-- Product Mini Info -->
            <div class="flex items-center gap-3 p-3 bg-slate-800/60 rounded-xl border border-slate-700 relative z-10">
                <div class="w-12 h-12 bg-slate-900 rounded-lg flex items-center justify-center shrink-0 overflow-hidden border <?php echo $is_pass_order ? 'border-yellow-500/30' : 'border-[#00f0ff]/30'; ?>">
                    <?php if($is_pass_order): ?>
                        <i class="fas fa-crown text-yellow-500 text-xl"></i>
                    <?php elseif(!empty($order['image_path'])): ?>
                        <img src="<?php echo $secure_main_url . $order['image_path']; ?>" class="w-full h-full object-cover">
                    <?php elseif(!empty($order['cat_image']) && !$is_cat_image_legacy_icon): ?>
                        <img src="<?php echo $secure_main_url . $order['cat_image']; ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <i class="fas <?php echo htmlspecialchars($is_cat_image_legacy_icon ? $order['cat_image'] : 'fa-cube'); ?> text-[#00f0ff] text-lg"></i>
                    <?php endif; ?>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="text-white font-bold text-xs truncate mb-0.5"><?php echo htmlspecialchars($order['product_name']); ?></div>
                    <div class="text-[10px] text-green-400 font-mono font-bold bg-green-900/20 px-1.5 py-0.5 rounded border border-green-900/30 inline-block">
                        <?php echo number_format($order['total_price_paid']); ?> Ks
                    </div>
                </div>
            </div>
            
            <?php if(!$is_pass_order): ?>
            <!-- Status Manager -->
            <form method="POST" class="mt-4 relative z-10 flex gap-2">
                <select name="status" class="flex-1 h-11 bg-slate-900 border border-slate-600 rounded-xl pl-3 pr-8 text-white text-xs md:text-sm focus:border-[#00f0ff] outline-none cursor-pointer shadow-inner appearance-none font-bold">
                    <option value="pending" <?php echo $order['status']=='pending'?'selected':''; ?>>🟡 Awaiting Auth</option>
                    <option value="active" <?php echo $order['status']=='active'?'selected':''; ?>>🟢 Complete (Active)</option>
                    <option value="rejected" <?php echo $order['status']=='rejected'?'selected':''; ?>>🔴 Terminate</option>
                </select>
                <button type="submit" name="update_status" class="h-11 bg-gradient-to-r from-blue-600 to-[#00f0ff] text-slate-900 px-4 rounded-xl font-black text-sm shadow-[0_0_15px_rgba(0,240,255,0.3)] shrink-0 flex items-center justify-center transition active:scale-95">
                    <i class="fas fa-save"></i>
                </button>
            </form>
            <?php endif; ?>
        </div>

        <!-- Identity & Form Data -->
        <div class="bg-slate-900/60 p-5 rounded-2xl border border-slate-700 shadow-lg shrink-0">
            <h3 class="font-bold <?php echo $is_pass_order ? 'text-yellow-500' : 'text-[#00f0ff]'; ?> text-[10px] uppercase tracking-widest border-b border-slate-700/50 pb-2 mb-3 flex items-center gap-2">
                <i class="fas fa-fingerprint"></i> Identity & Input
            </h3>
            
            <div class="space-y-3 text-xs md:text-sm">
                <div class="flex justify-between items-center bg-slate-800/50 p-2.5 rounded-lg border border-slate-700/50">
                    <span class="text-slate-400 font-bold uppercase tracking-wider text-[9px]">User</span>
                    <span class="text-white font-bold truncate max-w-[150px]">@<?php echo htmlspecialchars($order['username']); ?></span>
                </div>
                <div class="flex justify-between items-center bg-slate-800/50 p-2.5 rounded-lg border border-slate-700/50">
                    <span class="text-slate-400 font-bold uppercase tracking-wider text-[9px]">Txn ID</span>
                    <span class="font-mono text-yellow-400 font-bold select-all"><?php echo htmlspecialchars($order['transaction_last_6']); ?></span>
                </div>
            </div>

            <?php if(!empty($order['form_data'])): ?>
                <div class="mt-4 pt-4 border-t border-slate-700/50 space-y-2">
                    <?php 
                        $formData = json_decode($order['form_data'], true);
                        if(is_array($formData)) {
                            foreach($formData as $key => $val): 
                    ?>
                        <div class="bg-slate-800/80 p-2.5 rounded-lg border border-slate-600 shadow-inner flex flex-col gap-0.5">
                            <span class="text-[9px] text-slate-400 uppercase font-bold tracking-wider"><?php echo htmlspecialchars($key); ?></span>
                            <span class="text-xs <?php echo $is_pass_order ? 'text-yellow-400' : 'text-[#00f0ff]'; ?> font-mono font-bold select-all break-all"><?php echo htmlspecialchars($val); ?></span>
                        </div>
                    <?php 
                            endforeach; 
                        } 
                    ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Payment Proof -->
        <div class="bg-slate-900/60 p-5 rounded-2xl border border-slate-700 shadow-lg shrink-0">
            <h3 class="font-bold text-slate-400 mb-3 text-[10px] uppercase tracking-widest flex items-center gap-2"><i class="fas fa-file-invoice"></i> Verification Image</h3>
            <?php if(!empty($order['proof_image_path'])): ?>
                <div class="relative group overflow-hidden rounded-xl border border-slate-600 cursor-zoom-in bg-slate-950 aspect-video flex items-center justify-center shadow-inner" onclick="openLightbox('<?php echo $secure_main_url . $order['proof_image_path']; ?>')">
                    <img src="<?php echo $secure_main_url . $order['proof_image_path']; ?>" class="w-full h-full object-contain group-hover:opacity-50 transition duration-300">
                    <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition duration-300">
                        <span class="bg-[#00f0ff] text-slate-900 font-black text-[10px] uppercase tracking-widest px-3 py-1.5 rounded-lg shadow-lg"><i class="fas fa-search-plus mr-1"></i> Expand</span>
                    </div>
                </div>
            <?php else: ?>
                <div class="p-6 bg-red-900/20 text-red-400 text-xs rounded-xl border border-red-500/30 text-center font-bold flex flex-col items-center gap-2">
                    <i class="fas fa-image-slash text-2xl"></i> No Image Data
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- RIGHT PANEL: Live Chat & Fulfillment       -->
    <!-- NEW: Fully isolated flex terminal window   -->
    <!-- ========================================== -->
    <div id="colChat" class="w-full lg:w-2/3 xl:w-3/4 hidden lg:flex flex-col bg-slate-900/90 rounded-3xl border border-slate-700 shadow-2xl relative flex-1 min-h-[500px] lg:min-h-0 overflow-hidden">
        
        <?php if($is_pass_order): ?>
            <!-- Agent Pass Control Panel (Replaces Chat) -->
            <div class="flex-grow flex flex-col items-center justify-center relative p-6 text-center h-full overflow-y-auto custom-scrollbar">
                <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PHBhdHRlcm4gaWQ9ImdyaWQiIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCIgcGF0dGVyblVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+PHBhdGggZD0iTSAwIDEwIEwgNDAgMTAgTSAxMCAwIEwgMTAgNDAiIGZpbGw9Im5vbmUiIHN0cm9rZT0icmdiYSgyMzQsIDE3OSwgOCwgMC4wNSkiIHN0cm9rZS13aWR0aD0iMSIvPjwvcGF0dGVybj48L2RlZnM+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0idXJsKCNncmlkKSIvPjwvc3ZnPg==')] opacity-50 pointer-events-none"></div>
                <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-64 h-64 bg-yellow-500/10 rounded-full blur-[100px] pointer-events-none"></div>

                <div class="w-20 h-20 bg-yellow-500/10 rounded-full flex items-center justify-center text-yellow-500 text-4xl mb-4 border border-yellow-500/30 shadow-[0_0_30px_rgba(234,179,8,0.2)] relative z-10 shrink-0">
                    <i class="fas fa-crown"></i>
                </div>
                
                <h3 class="text-2xl font-black text-white mb-2 relative z-10 tracking-tight shrink-0">Agent Tier Authorization</h3>
                <p class="text-slate-400 text-sm max-w-sm mx-auto mb-8 relative z-10 shrink-0">
                    Verify the payment screenshot. If correct, approve the request to automatically grant reseller privileges.
                </p>

                <div class="w-full max-w-sm bg-slate-900 border border-slate-700 p-5 rounded-2xl shadow-inner relative z-10 shrink-0">
                    <div class="flex items-center justify-between mb-5">
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Current Status</span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider <?php echo $order['status'] == 'pending' ? 'bg-yellow-500/20 text-yellow-400' : ($order['status'] == 'active' ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'); ?>">
                            <?php echo $order['status']; ?>
                        </span>
                    </div>
                    
                    <form method="POST" class="grid grid-cols-2 gap-3">
                        <button type="submit" name="update_status" value="active" <?php echo $order['status'] == 'active' ? 'disabled' : ''; ?> class="h-12 bg-green-600 hover:bg-green-500 text-white font-bold rounded-xl shadow-[0_0_15px_rgba(16,185,129,0.3)] transition active:scale-95 flex items-center justify-center gap-2 text-xs <?php echo $order['status'] == 'active' ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                            <i class="fas fa-check-circle"></i> Approve
                        </button>
                        <button type="submit" name="update_status" value="rejected" <?php echo $order['status'] == 'rejected' ? 'disabled' : ''; ?> class="h-12 bg-slate-800 hover:bg-red-600 text-slate-300 hover:text-white font-bold rounded-xl border border-slate-600 transition shadow-sm flex items-center justify-center gap-2 text-xs <?php echo $order['status'] == 'rejected' ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                            <i class="fas fa-times-circle"></i> Reject
                        </button>
                    </form>
                </div>
            </div>

        <?php else: ?>
            <!-- Messenger Style Product View (Strictly bounded flex terminal) -->
            <div class="flex-1 flex flex-col min-h-0 w-full h-full relative z-10 bg-slate-950/50 rounded-2xl border border-[#00f0ff]/20 shadow-[inset_0_0_30px_rgba(0,240,255,0.05)] overflow-hidden m-1">
                
                <!-- Sticky Chat Header (Fulfillment Hub) -->
                <div class="bg-slate-800/95 backdrop-blur-md border-b border-[#00f0ff]/20 p-3 sm:p-4 shrink-0 shadow-sm z-20 flex flex-col gap-3">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-[#00f0ff]/10 flex items-center justify-center text-[#00f0ff] border border-[#00f0ff]/30 shadow-[0_0_10px_rgba(0,240,255,0.2)]">
                                <i class="fas fa-bolt text-sm"></i>
                            </div>
                            <div>
                                <h3 class="text-xs font-black text-white tracking-wide">Fulfillment Hub</h3>
                                <p class="text-[9px] text-[#00f0ff] uppercase tracking-widest font-mono">Encrypted Terminal</p>
                            </div>
                        </div>
                        <?php if($order['delivery_type'] === 'unique' && empty($available_keys)): ?>
                            <a href="index.php?page=keys&product_id=<?php echo $order['product_id']; ?>" class="bg-red-600 text-white px-3 py-1.5 rounded-lg text-[10px] font-bold uppercase tracking-wider flex items-center gap-1.5 shadow-sm hover:bg-red-500 transition-colors">
                                <i class="fas fa-exclamation-triangle"></i> Restock
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Quick Keys / Universal Data Injection -->
                    <div>
                        <?php if($order['delivery_type'] === 'unique' && !empty($available_keys)): ?>
                            <div class="flex gap-2 overflow-x-auto pb-1 custom-scrollbar snap-x">
                                <?php foreach($available_keys as $k): ?>
                                    <button type="button" onclick="autoPasteKey('<?php echo addslashes($k['key_content']); ?>')" class="snap-start bg-slate-900 border border-slate-700 rounded-lg py-2 px-3 min-w-[180px] flex justify-between items-center group hover:border-[#00f0ff]/50 transition-colors shrink-0 cursor-pointer">
                                        <code class="text-[10px] text-green-400 font-mono font-bold truncate mr-2"><?php echo htmlspecialchars($k['key_content']); ?></code>
                                        <i class="fas fa-paper-plane text-slate-500 group-hover:text-[#00f0ff] text-xs"></i>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif($order['delivery_type'] === 'universal'): ?>
                            <div class="bg-slate-900 border border-slate-700 rounded-lg p-2 flex justify-between items-center group shadow-inner">
                                <div class="text-[10px] text-slate-300 font-mono font-bold truncate pr-2"><?php echo htmlspecialchars($order['universal_content']); ?></div>
                                <button type="button" onclick="autoPasteKey('<?php echo addslashes($order['universal_content']); ?>')" class="bg-slate-700 hover:bg-[#00f0ff] hover:text-slate-900 text-white text-[9px] font-black px-3 py-1.5 rounded-md transition shadow-sm uppercase tracking-widest shrink-0">
                                    Inject
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Dynamic SPA Chat Area -->
                <!-- min-h-0 allows the flex item to scroll properly within bounded parent -->
                <div class="flex-1 min-h-0 overflow-y-auto p-4 chat-bg-pattern relative z-0 scroll-smooth" id="chatBox">
                    <div class="flex items-center justify-center h-full text-slate-500" id="chatLoading">
                        <i class="fas fa-circle-notch fa-spin text-2xl text-[#00f0ff]"></i>
                    </div>
                    <!-- Messages populated by AJAX -->
                </div>

                <!-- Chat Input Area (Fixed at bottom of terminal) -->
                <div class="bg-slate-800/95 backdrop-blur-md border-t border-[#00f0ff]/30 shrink-0 z-20">
                    <!-- Quick Replies -->
                    <div class="px-3 py-2 flex gap-2 overflow-x-auto hide-scrollbar border-b border-slate-700/50 bg-slate-900/30">
                        <?php foreach($quick_replies as $qr): ?>
                            <button type="button" onclick="insertQuickReply('<?php echo addslashes($qr); ?>')" class="whitespace-nowrap bg-slate-800 border border-slate-600 text-slate-300 hover:text-white hover:border-[#00f0ff] rounded-full px-3 py-1.5 text-[10px] font-medium transition-colors shrink-0 shadow-sm">
                                <?php echo htmlspecialchars((strlen($qr) > 25 ? substr($qr, 0, 25).'...' : $qr)); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <form id="adminChatForm" class="p-2 sm:p-3 flex items-end gap-2">
                        <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                        
                        <!-- AI Enhance Tool -->
                        <button type="button" onclick="enhanceText()" class="w-10 h-10 md:w-11 md:h-11 rounded-full bg-slate-700 text-purple-400 hover:text-white hover:bg-purple-600 flex items-center justify-center shrink-0 transition shadow-sm border border-slate-600" title="AI Enhance">
                            <i class="fas fa-magic text-sm"></i>
                        </button>

                        <!-- Auto-expanding Textarea Wrapper -->
                        <div class="flex-grow relative bg-slate-900 border border-slate-600 rounded-[20px] focus-within:border-[#00f0ff] focus-within:shadow-[0_0_15px_rgba(0,240,255,0.2)] transition-all flex items-end overflow-hidden min-h-[44px]">
                            
                            <textarea id="chatInput" name="message" rows="1" placeholder="Transmit data..." required 
                                      class="w-full bg-transparent py-3 pl-4 pr-12 text-[13px] md:text-sm text-white outline-none resize-none custom-scrollbar leading-snug max-h-[120px]" 
                                      oninput="this.style.height = 'auto'; this.style.height = (this.scrollHeight < 120 ? this.scrollHeight : 120) + 'px'"></textarea>
                            
                            <!-- Secure Toggle -->
                            <div class="absolute right-2 bottom-1.5 md:bottom-2">
                                <label class="cursor-pointer flex items-center justify-center w-8 h-8 rounded-full bg-slate-800 hover:bg-slate-700 transition group border border-transparent hover:border-slate-600" title="Send as Credential (Encrypted Format)">
                                    <input type="checkbox" id="secureToggle" name="is_credential" value="1" class="hidden peer">
                                    <i class="fas fa-shield-alt text-slate-400 peer-checked:text-green-400 peer-checked:animate-pulse text-xs transition-colors"></i>
                                </label>
                            </div>
                        </div>

                        <!-- Send Button -->
                        <button type="submit" class="w-10 h-10 md:w-11 md:h-11 rounded-full bg-gradient-to-r from-blue-600 to-[#00f0ff] hover:from-blue-500 hover:to-[#00f0ff] text-slate-900 flex items-center justify-center shrink-0 transition-all shadow-[0_0_15px_rgba(0,240,255,0.3)] active:scale-90">
                            <i class="fas fa-paper-plane text-sm ml-[-2px] mt-[1px]"></i>
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Lightbox Element -->
<div id="proofLightbox" class="lightbox" onclick="closeLightbox()">
    <span class="close-lightbox"><i class="fas fa-times"></i></span>
    <img id="lightboxImg" class="lightbox-content transform scale-95 transition-transform duration-300">
</div>

<script>
    // --- Mobile Tab Switching Logic ---
    function switchMobileTab(tab) {
        const colInfo = document.getElementById('colInfo');
        const colChat = document.getElementById('colChat');
        const btnInfo = document.getElementById('tabBtnInfo');
        const btnChat = document.getElementById('tabBtnChat');
        
        if (tab === 'info') {
            colInfo.classList.remove('hidden');
            colInfo.classList.add('flex');
            colChat.classList.add('hidden');
            colChat.classList.remove('flex');
            
            btnInfo.className = "flex-1 py-2.5 text-xs font-bold uppercase tracking-wider rounded-lg transition-all bg-slate-700 text-white shadow-sm";
            btnChat.className = "flex-1 py-2.5 text-xs font-bold uppercase tracking-wider rounded-lg transition-all text-slate-400 hover:text-white relative";
        } else {
            colChat.classList.remove('hidden');
            colChat.classList.add('flex');
            colInfo.classList.add('hidden');
            colInfo.classList.remove('flex');
            
            btnChat.className = "flex-1 py-2.5 text-xs font-bold uppercase tracking-wider rounded-lg transition-all bg-slate-700 text-white shadow-sm relative";
            btnInfo.className = "flex-1 py-2.5 text-xs font-bold uppercase tracking-wider rounded-lg transition-all text-slate-400 hover:text-white";
            
            // Scroll chat to bottom when opened
            const chatBox = document.getElementById('chatBox');
            if(chatBox) chatBox.scrollTop = chatBox.scrollHeight;
        }
    }

    // --- Chat Logic ---
    const orderId = <?php echo $order_id; ?>;
    const isPassOrder = <?php echo $is_pass_order ? 'true' : 'false'; ?>;
    const chatBox = document.getElementById('chatBox');
    const chatInput = document.getElementById('chatInput');
    const secureToggle = document.getElementById('secureToggle');
    
    let isUserScrolling = false;
    let lastChatHtml = '';

    if (!isPassOrder && chatBox) {
        // Detect scrolling to prevent auto-scroll if reading history
        chatBox.addEventListener('scroll', () => {
            const isAtBottom = chatBox.scrollHeight - chatBox.scrollTop <= chatBox.clientHeight + 50;
            isUserScrolling = !isAtBottom;
        });

        function fetchChat() {
            if(!orderId) return;
            
            fetch(`index.php?page=order_detail&id=${orderId}&ajax=1&_=${Date.now()}`)
                .then(res => res.text())
                .then(html => {
                    const loading = document.getElementById('chatLoading');
                    if(loading) loading.remove();

                    // FIX: Extract ONLY the chat payload to prevent Admin Header/Sidebar bleed
                    const match = html.match(/<!--CHAT_PAYLOAD_START-->([\s\S]*?)<!--CHAT_PAYLOAD_END-->/);
                    const chatContent = match ? match[1] : html;

                    if(chatContent.trim() !== '' && chatContent !== lastChatHtml) {
                        chatBox.innerHTML = chatContent;
                        lastChatHtml = chatContent;
                        if(!isUserScrolling) {
                            chatBox.scrollTop = chatBox.scrollHeight;
                        }
                    }
                })
                .catch(err => console.error('Sync Error:', err));
        }

        // Init Polling
        fetchChat();
        setInterval(fetchChat, 3000);

        // Form Submission
        const chatForm = document.getElementById('adminChatForm');
        if (chatForm) {
            chatForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                if(!chatInput || !chatInput.value.trim()) return;

                const formData = new FormData(this);
                formData.append('ajax_msg', '1');
                
                // Reset input UI instantly
                chatInput.value = ''; 
                chatInput.style.height = '44px'; // Base height
                
                try {
                    await fetch(`index.php?page=order_detail&id=${orderId}`, {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    isUserScrolling = false;
                    if(secureToggle) secureToggle.checked = false;
                    fetchChat(); 
                } catch(err) { console.error('Transmission failed:', err); }
            });
        }
        
        // Enter key to submit (Shift+Enter for new line)
        if(chatInput) {
            chatInput.addEventListener('keydown', function(e) {
                if(e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    chatForm.dispatchEvent(new Event('submit'));
                }
            });
        }
    }

    // --- Helpers ---
    function insertQuickReply(text) {
        if(chatInput) {
            chatInput.value = text;
            chatInput.focus();
            chatInput.style.height = 'auto';
            chatInput.style.height = chatInput.scrollHeight + 'px';
            if(secureToggle) secureToggle.checked = false;
        }
    }

    function autoPasteKey(keyText) {
        if(chatInput) {
            chatInput.value = keyText;
            chatInput.focus();
            chatInput.style.height = 'auto';
            chatInput.style.height = chatInput.scrollHeight + 'px';
            if(secureToggle) secureToggle.checked = true; // Auto secure for keys
            
            // Visual flash
            const wrapper = chatInput.parentElement;
            wrapper.classList.add('border-[#00f0ff]', 'shadow-[0_0_20px_rgba(0,240,255,0.4)]');
            setTimeout(() => {
                wrapper.classList.remove('border-[#00f0ff]', 'shadow-[0_0_20px_rgba(0,240,255,0.4)]');
            }, 600);
        }
    }

    function enhanceText() {
        if(chatInput) {
            let val = chatInput.value.trim();
            if(val.length > 2) {
                val = val.charAt(0).toUpperCase() + val.slice(1);
                if(!val.endsWith('.') && !val.endsWith('!') && !val.endsWith('?')) val += '.';
                if(!val.includes('Thank')) val += '\n\nThank you for choosing DigitalMarketplaceMM!';
                
                chatInput.value = val;
                chatInput.style.height = 'auto';
                chatInput.style.height = chatInput.scrollHeight + 'px';
                
                // Visual flash
                const wrapper = chatInput.parentElement;
                wrapper.classList.add('border-purple-500', 'shadow-[0_0_20px_rgba(168,85,247,0.4)]');
                setTimeout(() => wrapper.classList.remove('border-purple-500', 'shadow-[0_0_20px_rgba(168,85,247,0.4)]'), 600);
            }
        }
    }

    // --- Lightbox ---
    function openLightbox(src) {
        const lightbox = document.getElementById('proofLightbox');
        const img = document.getElementById('lightboxImg');
        img.src = src;
        lightbox.style.display = 'flex';
        setTimeout(() => img.classList.remove('scale-95'), 10);
    }
    
    function closeLightbox() {
        const lightbox = document.getElementById('proofLightbox');
        const img = document.getElementById('lightboxImg');
        img.classList.add('scale-95');
        setTimeout(() => { lightbox.style.display = 'none'; }, 300);
    }
</script>