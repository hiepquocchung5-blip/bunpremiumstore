<?php
// admin/settings.php

// 1. Handle Change Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    
    $stmt = $pdo->prepare("SELECT password FROM adm_user WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $hash = $stmt->fetchColumn();

    if (password_verify($current, $hash)) {
        if ($new !== $confirm) {
            $error_pw = "New passwords do not match.";
        } elseif (strlen($new) >= 8) {
            $new_hash = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE adm_user SET password = ? WHERE id = ?")->execute([$new_hash, $_SESSION['admin_id']]);
            $success_pw = "Master key updated successfully.";
        } else {
            $error_pw = "New password must be at least 8 characters.";
        }
    } else {
        $error_pw = "Incorrect current password.";
    }
}

// 2. Handle Manual System Maintenance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_maintenance'])) {
    if ($_SESSION['admin_role'] === 'super_admin') {
        try {
            // Task 1: Expire old passes
            $pdo->query("UPDATE user_passes SET status = 'expired' WHERE status = 'active' AND expires_at < NOW()");
            
            // Task 2: Delete unverified users older than 3 days
            $pdo->query("DELETE FROM users WHERE is_verified = 0 AND created_at < DATE_SUB(NOW(), INTERVAL 3 DAY)");
            
            // Task 3: Reject stale pending orders (> 7 days)
            $pdo->query("UPDATE orders SET status = 'rejected' WHERE status = 'pending' AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
            
            $success_sys = "System maintenance completed successfully.";
        } catch (Exception $e) {
            $error_sys = "Maintenance failed: " . $e->getMessage();
        }
    } else {
        $error_sys = "Permission denied. Super Admin required.";
    }
}

// 3. Gather System Telemetry
$db_size_query = $pdo->query("SELECT SUM(data_length + index_length) / 1024 / 1024 AS size FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'");
$db_size = round($db_size_query->fetchColumn(), 2) . ' MB';

$total_admins = $pdo->query("SELECT COUNT(*) FROM adm_user")->fetchColumn();
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-white tracking-tight">System Settings</h1>
    <p class="text-slate-400 text-sm mt-1">Manage security credentials and system maintenance.</p>
</div>

<!-- Alert Banner for Staff Management Move -->
<div class="bg-blue-900/20 border border-blue-500/30 rounded-xl p-4 mb-8 flex items-center justify-between shadow-lg">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-blue-500/20 flex items-center justify-center text-blue-400 shrink-0">
            <i class="fas fa-info-circle"></i>
        </div>
        <div>
            <h4 class="text-white font-bold text-sm">Staff Management has moved!</h4>
            <p class="text-slate-400 text-xs mt-0.5">Admin creation and roles are now managed in the dedicated Staff module.</p>
        </div>
    </div>
    <a href="<?php echo admin_url('admins'); ?>" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-lg text-sm font-bold transition whitespace-nowrap">
        Go to Staff Settings
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    
    <!-- LEFT COLUMN: Security -->
    <div class="space-y-6">
        
        <!-- Change Password -->
        <div class="bg-slate-800 p-6 rounded-2xl border border-slate-700 shadow-xl relative overflow-hidden group">
            <!-- Neon Accent Line -->
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-red-600 to-red-400"></div>
            
            <div class="absolute -right-6 -top-6 w-32 h-32 bg-red-500/5 rounded-full blur-2xl pointer-events-none group-hover:bg-red-500/10 transition"></div>

            <h3 class="font-bold text-white mb-6 flex items-center gap-2 relative z-10">
                <i class="fas fa-lock text-red-500"></i> Update Master Key
            </h3>
            
            <?php if(isset($error_pw)) echo "<div class='text-red-400 text-sm mb-4 bg-red-900/20 p-3 rounded-lg border border-red-500/30 flex items-center gap-2'><i class='fas fa-exclamation-triangle'></i> $error_pw</div>"; ?>
            <?php if(isset($success_pw)) echo "<div class='text-green-400 text-sm mb-4 bg-green-900/20 p-3 rounded-lg border border-green-500/30 flex items-center gap-2'><i class='fas fa-check-circle'></i> $success_pw</div>"; ?>

            <form method="POST" class="space-y-4 relative z-10">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Current Password</label>
                    <div class="relative">
                        <i class="fas fa-key absolute left-3 top-3 text-slate-500 text-sm"></i>
                        <input type="password" name="current_password" placeholder="Verify identity" required class="w-full bg-slate-900 border border-slate-600 rounded-lg py-2.5 pl-9 pr-3 text-white text-sm focus:border-red-500 outline-none shadow-inner transition">
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">New Password</label>
                        <input type="password" name="new_password" placeholder="Min 8 chars" required class="w-full bg-slate-900 border border-slate-600 rounded-lg p-2.5 text-white text-sm focus:border-red-500 outline-none shadow-inner transition">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Confirm New</label>
                        <input type="password" name="confirm_password" placeholder="Repeat password" required class="w-full bg-slate-900 border border-slate-600 rounded-lg p-2.5 text-white text-sm focus:border-red-500 outline-none shadow-inner transition">
                    </div>
                </div>
                
                <button type="submit" name="change_password" class="w-full bg-slate-700 hover:bg-slate-600 text-white font-bold py-3 rounded-lg text-sm transition border border-slate-600 mt-2 shadow-lg">
                    Update Security Credentials
                </button>
            </form>
        </div>

    </div>

    <!-- RIGHT COLUMN: System & Telemetry -->
    <div class="space-y-6">
        
        <!-- Server Telemetry -->
        <div class="bg-slate-800 p-6 rounded-2xl border border-slate-700 shadow-xl">
            <h3 class="font-bold text-white mb-6 flex items-center gap-2 border-b border-slate-700 pb-2">
                <i class="fas fa-server text-[#00f0ff]"></i> System Telemetry
            </h3>
            
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-slate-900/50 p-4 rounded-xl border border-slate-700/50 flex flex-col items-center justify-center text-center">
                    <span class="text-[10px] text-slate-500 uppercase font-bold tracking-wider mb-1">Database Size</span>
                    <span class="text-xl font-mono font-bold text-[#00f0ff]"><?php echo $db_size; ?></span>
                </div>
                <div class="bg-slate-900/50 p-4 rounded-xl border border-slate-700/50 flex flex-col items-center justify-center text-center">
                    <span class="text-[10px] text-slate-500 uppercase font-bold tracking-wider mb-1">Active Admins</span>
                    <span class="text-xl font-mono font-bold text-purple-400"><?php echo $total_admins; ?></span>
                </div>
            </div>

            <ul class="space-y-3 text-sm mt-6 bg-slate-900 rounded-xl p-4 border border-slate-700">
                <li class="flex justify-between items-center text-slate-400">
                    <span class="flex items-center gap-2"><i class="far fa-clock text-slate-500"></i> Server Time</span> 
                    <span class="text-white font-mono bg-slate-800 px-2 py-1 rounded"><?php echo date('Y-m-d H:i:s'); ?></span>
                </li>
                <li class="flex justify-between items-center text-slate-400">
                    <span class="flex items-center gap-2"><i class="fab fa-php text-slate-500"></i> PHP Version</span> 
                    <span class="text-white font-mono bg-slate-800 px-2 py-1 rounded"><?php echo phpversion(); ?></span>
                </li>
                <li class="flex justify-between items-center text-slate-400">
                    <span class="flex items-center gap-2"><i class="fas fa-network-wired text-slate-500"></i> Active Connection</span> 
                    <span class="text-green-400 font-bold bg-green-500/10 px-2 py-1 rounded flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse"></span> Secure</span>
                </li>
            </ul>
        </div>

        <!-- Database Maintenance -->
        <div class="bg-slate-800 p-6 rounded-2xl border border-slate-700 shadow-xl">
            <h3 class="font-bold text-white mb-2 flex items-center gap-2">
                <i class="fas fa-broom text-yellow-500"></i> Database Maintenance
            </h3>
            <p class="text-xs text-slate-400 mb-4 line-clamp-2">Manually trigger the cron-job routines to clean up expired passes, unverified users, and stale pending orders.</p>
            
            <?php if(isset($success_sys)) echo "<div class='text-green-400 text-sm mb-4 bg-green-900/20 p-2 rounded border border-green-500/30'><i class='fas fa-check'></i> $success_sys</div>"; ?>
            <?php if(isset($error_sys)) echo "<div class='text-red-400 text-sm mb-4 bg-red-900/20 p-2 rounded border border-red-500/30'><i class='fas fa-times'></i> $error_sys</div>"; ?>

            <form method="POST">
                <button type="submit" name="run_maintenance" class="w-full bg-yellow-600/20 hover:bg-yellow-600 border border-yellow-500/50 hover:border-yellow-500 text-yellow-500 hover:text-slate-900 font-bold py-3 rounded-lg text-sm transition duration-300 flex justify-center items-center gap-2" onclick="return confirm('Run full system cleanup? This action cannot be undone.')">
                    <i class="fas fa-magic"></i> Run Cleanup Protocol
                </button>
            </form>
        </div>

    </div>
</div>