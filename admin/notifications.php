<?php
// admin/notifications.php
// PRODUCTION v2.0 - Push Notification Command Center with Macro Injection

require_once dirname(__DIR__) . '/includes/PushService.php';

// 1. Handle Broadcast Execution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute_broadcast'])) {
    
    // Prevent timeout for large broadcasts
    set_time_limit(0); 

    $target = $_POST['target_audience']; // 'all', 'agents', or specific user ID
    $title = trim($_POST['push_title']);
    $body = trim($_POST['push_body']);
    $url = trim($_POST['push_url']) ?: null;

    if (empty($title) || empty($body)) {
        $error = "Transmission aborted: Title and Body payload required.";
    } else {
        try {
            $push = new PushService($pdo);
            $sent_count = 0;

            if ($target === 'all') {
                $sent_count = $push->sendToAll($title, $body, $url);
                $target_name = "Global Network";
            } elseif ($target === 'agents') {
                $sent_count = $push->sendToAgents($title, $body, $url);
                $target_name = "Active Resellers";
            } elseif (is_numeric($target)) {
                $sent_count = $push->sendToUser((int)$target, $title, $body, $url);
                
                // Fetch username for success message
                $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                $stmt->execute([(int)$target]);
                $target_name = "Operative @" . $stmt->fetchColumn();
            } else {
                throw new Exception("Invalid target parameter.");
            }

            if ($sent_count > 0) {
                $success = "Payload delivered successfully to <strong>$sent_count</strong> devices targeting: <strong>$target_name</strong>.";
            } else {
                $error = "Target node(s) do not have active Push Subscriptions.";
            }

        } catch (Exception $e) {
            $error = "Matrix Error: " . $e->getMessage();
        }
    }
}

// 2. Fetch Users for Dropdown (Only users with active push subscriptions)
$stmt = $pdo->query("
    SELECT u.id, u.username, COUNT(ps.id) as devices 
    FROM users u 
    JOIN push_subscriptions ps ON u.id = ps.user_id 
    GROUP BY u.id 
    ORDER BY u.username ASC
");
$subscribed_users = $stmt->fetchAll();

// 3. Fetch Active Coupons for Macro Panel
$active_coupons = $pdo->query("
    SELECT * FROM coupons 
    WHERE is_active = 1 AND expires_at > NOW() 
    ORDER BY created_at DESC LIMIT 3
")->fetchAll();

// 4. Fetch Flash Sales for Macro Panel
$flash_sales = $pdo->query("
    SELECT id, name, price, sale_price 
    FROM products 
    WHERE sale_price IS NOT NULL AND sale_price < price 
    ORDER BY id DESC LIMIT 3
")->fetchAll();

// Telemetry
$total_subs = $pdo->query("SELECT COUNT(*) FROM push_subscriptions")->fetchColumn();
$total_active_coupons = $pdo->query("SELECT COUNT(*) FROM coupons WHERE is_active = 1 AND expires_at > NOW()")->fetchColumn();
$total_flash_sales = $pdo->query("SELECT COUNT(*) FROM products WHERE sale_price IS NOT NULL AND sale_price < price")->fetchColumn();
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-3xl font-bold text-white tracking-tight flex items-center gap-3">
            Push Matrix <i class="fas fa-satellite-dish text-[#00f0ff] animate-pulse"></i>
        </h1>
        <p class="text-slate-400 text-sm mt-1">Broadcast Web Push notifications and marketing payloads.</p>
    </div>
</div>

<?php if(isset($success)): ?>
    <div class="bg-green-500/10 border border-green-500/30 text-green-400 p-4 rounded-xl mb-6 flex items-center gap-3 shadow-[0_0_15px_rgba(34,197,94,0.15)] animate-fade-in-down">
        <i class="fas fa-check-circle text-xl"></i> <span><?php echo $success; ?></span>
    </div>
<?php endif; ?>

<?php if(isset($error)): ?>
    <div class="bg-red-500/10 border border-red-500/30 text-red-400 p-4 rounded-xl mb-6 flex items-center gap-3 shadow-[0_0_15px_rgba(239,68,68,0.15)] animate-pulse">
        <i class="fas fa-exclamation-triangle text-xl"></i> <span><?php echo $error; ?></span>
    </div>
<?php endif; ?>

<!-- MACRO INJECTION PANEL (ONE-CLICK NOTIFICATIONS) -->
<div class="mb-8 bg-slate-900/60 backdrop-blur-xl border border-slate-700/50 p-6 rounded-3xl shadow-xl relative overflow-hidden">
    <div class="absolute top-0 right-0 w-64 h-64 bg-blue-600/5 rounded-full blur-3xl pointer-events-none"></div>
    
    <h3 class="font-black text-white mb-4 uppercase tracking-widest text-xs flex items-center gap-2 border-b border-slate-700/50 pb-2">
        <i class="fas fa-bolt text-yellow-400"></i> One-Click Payload Macros
    </h3>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 relative z-10">
        
        <!-- Flash Sales Macros -->
        <div class="space-y-3">
            <h4 class="text-[10px] font-bold text-red-400 uppercase tracking-widest flex items-center gap-1.5"><i class="fas fa-fire animate-pulse"></i> Active Flash Sales</h4>
            <?php if(empty($flash_sales)): ?>
                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4 text-center text-slate-500 text-xs font-medium">
                    No active flash sales detected in the matrix.
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 gap-2.5">
                    <?php foreach($flash_sales as $sale): 
                        $discount_amt = $sale['price'] - $sale['sale_price'];
                        $title = "⚡ FLASH SALE: " . addslashes(htmlspecialchars($sale['name']));
                        $body = "Massive discount! Save " . format_admin_currency($discount_amt) . " instantly. Tap to decrypt this deal.";
                        $url = MAIN_SITE_URL . "index.php?module=shop&page=product&id=" . $sale['id'];
                    ?>
                        <button type="button" onclick="loadMacro('<?php echo $title; ?>', '<?php echo $body; ?>', '<?php echo $url; ?>')" class="text-left bg-slate-800 border border-red-500/20 hover:border-red-400 hover:bg-red-900/20 transition-all duration-300 rounded-xl p-3 shadow-inner group">
                            <div class="flex justify-between items-center mb-1">
                                <span class="font-bold text-white text-sm truncate group-hover:text-red-400 transition-colors pr-2"><?php echo htmlspecialchars($sale['name']); ?></span>
                                <span class="bg-red-500/10 text-red-400 text-[9px] font-black uppercase tracking-widest px-1.5 py-0.5 rounded border border-red-500/30 shrink-0">Inject</span>
                            </div>
                            <div class="text-xs font-mono text-slate-400">
                                <span class="line-through opacity-50 mr-1"><?php echo format_admin_currency($sale['price']); ?></span> 
                                <span class="text-green-400 font-bold"><?php echo format_admin_currency($sale['sale_price']); ?></span>
                            </div>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Coupon Macros -->
        <div class="space-y-3">
            <h4 class="text-[10px] font-bold text-yellow-400 uppercase tracking-widest flex items-center gap-1.5"><i class="fas fa-ticket-alt"></i> Active Promo Codes</h4>
            <?php if(empty($active_coupons)): ?>
                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4 text-center text-slate-500 text-xs font-medium">
                    No active promo codes detected in the matrix.
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 gap-2.5">
                    <?php foreach($active_coupons as $coupon): 
                        $title = "🎁 PROMO DROP: " . $coupon['discount_percent'] . "% OFF!";
                        $body = "Use code [" . addslashes(htmlspecialchars($coupon['code'])) . "] at checkout to instantly receive " . $coupon['discount_percent'] . "% off your order. Limited time only!";
                        $url = MAIN_SITE_URL . "index.php?module=shop&page=category";
                    ?>
                        <button type="button" onclick="loadMacro('<?php echo $title; ?>', '<?php echo $body; ?>', '<?php echo $url; ?>')" class="text-left bg-slate-800 border border-yellow-500/20 hover:border-yellow-400 hover:bg-yellow-900/20 transition-all duration-300 rounded-xl p-3 shadow-inner group">
                            <div class="flex justify-between items-center mb-1">
                                <span class="font-bold text-white text-sm group-hover:text-yellow-400 transition-colors">Code: <span class="font-mono text-[#00f0ff]"><?php echo htmlspecialchars($coupon['code']); ?></span></span>
                                <span class="bg-yellow-500/10 text-yellow-400 text-[9px] font-black uppercase tracking-widest px-1.5 py-0.5 rounded border border-yellow-500/30 shrink-0">Inject</span>
                            </div>
                            <div class="text-xs font-medium text-slate-400">
                                Saves <span class="text-green-400 font-bold"><?php echo $coupon['discount_percent']; ?>%</span> • Expires: <?php echo date('M d', strtotime($coupon['expires_at'])); ?>
                            </div>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- LEFT: Transmit Form -->
    <div class="lg:col-span-2">
        <div id="pushFormContainer" class="bg-slate-900/80 backdrop-blur-xl border border-[#00f0ff]/20 p-6 md:p-8 rounded-3xl shadow-[0_10px_30px_rgba(0,0,0,0.5)] relative overflow-hidden group transition-all duration-500">
            
            <div class="absolute -right-20 -top-20 w-48 h-48 bg-[#00f0ff]/10 rounded-full blur-3xl pointer-events-none group-hover:bg-[#00f0ff]/20 transition-colors duration-700"></div>

            <h3 class="font-bold text-white mb-6 border-b border-slate-700/50 pb-3 flex items-center gap-2 relative z-10">
                <i class="fas fa-broadcast-tower text-[#00f0ff]"></i> Payload Transmitter
            </h3>

            <form method="POST" id="pushForm" class="space-y-6 relative z-10">
                
                <!-- Target Audience -->
                <div class="bg-slate-800/50 p-5 rounded-2xl border border-slate-700 shadow-inner">
                    <label class="block text-[10px] font-black text-[#00f0ff] uppercase tracking-widest mb-3"><i class="fas fa-crosshairs"></i> Target Designation</label>
                    <div class="relative">
                        <select name="target_audience" required class="w-full bg-slate-950 border border-slate-600 rounded-xl py-3.5 pl-4 pr-10 text-white text-sm focus:border-[#00f0ff] outline-none appearance-none shadow-inner cursor-pointer">
                            <option value="all">🌐 Global Network (All Subscribed Devices)</option>
                            <option value="agents">👑 Active Resellers (Agent Tiers Only)</option>
                            <optgroup label="Specific Operatives">
                                <?php foreach($subscribed_users as $u): ?>
                                    <option value="<?php echo $u['id']; ?>">@<?php echo htmlspecialchars($u['username']); ?> (<?php echo $u['devices']; ?> Devices)</option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                        <i class="fas fa-chevron-down absolute right-4 top-4 text-slate-500 pointer-events-none text-xs"></i>
                    </div>
                </div>

                <!-- Payload -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Payload Title</label>
                        <input type="text" name="push_title" id="input_push_title" placeholder="e.g. Flash Sale Alert!" required maxlength="50"
                               class="w-full bg-slate-900 border border-slate-600 rounded-xl py-3 px-4 text-white font-bold focus:border-[#00f0ff] focus:ring-1 focus:ring-[#00f0ff] outline-none shadow-inner transition-all">
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Transmission Body</label>
                        <textarea name="push_body" id="input_push_body" rows="3" placeholder="Enter notification message..." required maxlength="150"
                                  class="w-full bg-slate-900 border border-slate-600 rounded-xl py-3 px-4 text-white text-sm focus:border-[#00f0ff] focus:ring-1 focus:ring-[#00f0ff] outline-none shadow-inner transition-all resize-none"></textarea>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Action URL (Optional)</label>
                        <div class="relative">
                            <i class="fas fa-link absolute left-4 top-3.5 text-slate-500 text-xs"></i>
                            <input type="text" name="push_url" id="input_push_url" placeholder="https://..." 
                                   class="w-full bg-slate-900 border border-slate-600 rounded-xl py-3 pl-10 pr-4 text-white text-sm focus:border-[#00f0ff] focus:ring-1 focus:ring-[#00f0ff] outline-none shadow-inner transition-all">
                        </div>
                    </div>
                </div>

                <button type="submit" name="execute_broadcast" class="w-full bg-gradient-to-r from-blue-600 to-[#00f0ff] hover:from-blue-500 hover:to-[#00f0ff] text-slate-900 font-black py-4 rounded-xl shadow-[0_0_20px_rgba(0,240,255,0.3)] transition transform active:scale-[0.98] uppercase tracking-widest flex justify-center items-center gap-2 group/btn mt-4">
                    <i class="fas fa-paper-plane group-hover/btn:animate-bounce"></i> Execute Broadcast
                </button>

            </form>
        </div>
    </div>

    <!-- RIGHT: Telemetry -->
    <div class="lg:col-span-1 space-y-6">
        
        <!-- Main Stats -->
        <div class="bg-slate-800 p-6 rounded-2xl border border-slate-700 shadow-xl text-center relative overflow-hidden group">
            <div class="absolute -right-4 -top-4 w-16 h-16 bg-blue-500/10 rounded-full blur-xl group-hover:bg-[#00f0ff]/20 transition-colors"></div>
            
            <div class="w-16 h-16 mx-auto bg-slate-900 rounded-2xl flex items-center justify-center text-[#00f0ff] border border-[#00f0ff]/30 shadow-inner mb-4 relative z-10">
                <i class="fas fa-mobile-alt text-2xl"></i>
            </div>
            
            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mb-1 relative z-10">Active Endpoints</p>
            <h3 class="text-4xl font-black text-white font-mono tracking-tighter relative z-10"><?php echo number_format($total_subs); ?></h3>
            
            <div class="mt-4 pt-4 border-t border-slate-700 flex items-center justify-center gap-2 text-xs text-green-400 font-bold uppercase tracking-wider relative z-10">
                <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span> Service Operational
            </div>
        </div>

        <!-- System Intelligence -->
        <div class="grid grid-cols-2 gap-4">
            <div class="bg-slate-800/50 p-4 rounded-xl border border-slate-700/50 text-center shadow-inner">
                <p class="text-[9px] text-slate-500 uppercase font-black tracking-widest mb-1">Live Sales</p>
                <p class="text-xl font-mono text-red-400 font-bold"><?php echo $total_flash_sales; ?></p>
            </div>
            <div class="bg-slate-800/50 p-4 rounded-xl border border-slate-700/50 text-center shadow-inner">
                <p class="text-[9px] text-slate-500 uppercase font-black tracking-widest mb-1">Active Promos</p>
                <p class="text-xl font-mono text-yellow-400 font-bold"><?php echo $total_active_coupons; ?></p>
            </div>
        </div>

        <!-- Guidelines -->
        <div class="bg-slate-800/50 p-6 rounded-2xl border border-slate-700 shadow-inner">
            <h4 class="text-xs font-black text-slate-300 uppercase tracking-widest mb-3 border-b border-slate-700 pb-2"><i class="fas fa-info-circle text-blue-400"></i> Protocol Info</h4>
            <ul class="space-y-3 text-xs text-slate-400 font-medium leading-relaxed">
                <li><strong class="text-white">Delivery Rate:</strong> Pushes are delivered instantly via browser Service Workers.</li>
                <li><strong class="text-white">Offline Nodes:</strong> If a user's device is offline, the payload is held by the push service provider until they reconnect.</li>
                <li><strong class="text-white">URL Routing:</strong> Ensure Action URLs start with <code class="bg-slate-900 px-1 rounded text-blue-300">https://</code>. Leave blank to default to their dashboard.</li>
            </ul>
        </div>
    </div>
</div>

<script>
    // Matrix Payload Injection Engine
    function loadMacro(title, body, url) {
        const titleInput = document.getElementById('input_push_title');
        const bodyInput = document.getElementById('input_push_body');
        const urlInput = document.getElementById('input_push_url');
        const formContainer = document.getElementById('pushFormContainer');

        // Inject Data
        titleInput.value = title;
        bodyInput.value = body;
        urlInput.value = url;

        // Visual Neon Feedback
        formContainer.classList.add('border-[#00f0ff]', 'shadow-[0_0_40px_rgba(0,240,255,0.4)]', 'scale-[1.02]');
        titleInput.classList.add('border-[#00f0ff]', 'ring-1', 'ring-[#00f0ff]', 'bg-blue-900/20');
        bodyInput.classList.add('border-[#00f0ff]', 'ring-1', 'ring-[#00f0ff]', 'bg-blue-900/20');
        urlInput.classList.add('border-[#00f0ff]', 'ring-1', 'ring-[#00f0ff]', 'bg-blue-900/20');

        // Smooth Scroll to Form
        formContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });

        // Remove Visual Feedback after 800ms
        setTimeout(() => {
            formContainer.classList.remove('border-[#00f0ff]', 'shadow-[0_0_40px_rgba(0,240,255,0.4)]', 'scale-[1.02]');
            titleInput.classList.remove('border-[#00f0ff]', 'ring-1', 'ring-[#00f0ff]', 'bg-blue-900/20');
            bodyInput.classList.remove('border-[#00f0ff]', 'ring-1', 'ring-[#00f0ff]', 'bg-blue-900/20');
            urlInput.classList.remove('border-[#00f0ff]', 'ring-1', 'ring-[#00f0ff]', 'bg-blue-900/20');
        }, 800);
    }
</script>