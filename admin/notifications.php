<?php
// admin/notifications.php
// PRODUCTION v1.0 - Push Notification Command Center

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

// Telemetry
$total_subs = $pdo->query("SELECT COUNT(*) FROM push_subscriptions")->fetchColumn();
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-3xl font-bold text-white tracking-tight flex items-center gap-3">
            Push Matrix <i class="fas fa-satellite-dish text-[#00f0ff] animate-pulse"></i>
        </h1>
        <p class="text-slate-400 text-sm mt-1">Broadcast Web Push notifications to connected operative devices.</p>
    </div>
</div>

<?php if(isset($success)): ?>
    <div class="bg-green-500/10 border border-green-500/30 text-green-400 p-4 rounded-xl mb-6 flex items-center gap-3 shadow-[0_0_15px_rgba(34,197,94,0.15)] animate-fade-in-down">
        <i class="fas fa-check-circle"></i> <span><?php echo $success; ?></span>
    </div>
<?php endif; ?>

<?php if(isset($error)): ?>
    <div class="bg-red-500/10 border border-red-500/30 text-red-400 p-4 rounded-xl mb-6 flex items-center gap-3 shadow-[0_0_15px_rgba(239,68,68,0.15)] animate-pulse">
        <i class="fas fa-exclamation-triangle"></i> <span><?php echo $error; ?></span>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- LEFT: Transmit Form -->
    <div class="lg:col-span-2">
        <div class="bg-slate-900/80 backdrop-blur-xl border border-[#00f0ff]/20 p-6 md:p-8 rounded-3xl shadow-[0_10px_30px_rgba(0,0,0,0.5)] relative overflow-hidden group">
            
            <div class="absolute -right-20 -top-20 w-48 h-48 bg-[#00f0ff]/10 rounded-full blur-3xl pointer-events-none group-hover:bg-[#00f0ff]/20 transition-colors duration-700"></div>

            <h3 class="font-bold text-white mb-6 border-b border-slate-700/50 pb-3 flex items-center gap-2 relative z-10">
                <i class="fas fa-broadcast-tower text-[#00f0ff]"></i> Initialize Transmission
            </h3>

            <form method="POST" class="space-y-6 relative z-10">
                
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
                        <input type="text" name="push_title" placeholder="e.g. Flash Sale Alert!" required maxlength="50"
                               class="w-full bg-slate-900 border border-slate-600 rounded-xl py-3 px-4 text-white font-bold focus:border-[#00f0ff] outline-none shadow-inner transition-colors">
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Transmission Body</label>
                        <textarea name="push_body" rows="3" placeholder="Enter notification message..." required maxlength="150"
                                  class="w-full bg-slate-900 border border-slate-600 rounded-xl py-3 px-4 text-white text-sm focus:border-[#00f0ff] outline-none shadow-inner transition-colors resize-none"></textarea>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Action URL (Optional)</label>
                        <div class="relative">
                            <i class="fas fa-link absolute left-4 top-3.5 text-slate-500 text-xs"></i>
                            <input type="text" name="push_url" placeholder="https://..." 
                                   class="w-full bg-slate-900 border border-slate-600 rounded-xl py-3 pl-10 pr-4 text-white text-sm focus:border-[#00f0ff] outline-none shadow-inner transition-colors">
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