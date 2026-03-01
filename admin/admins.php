<?php
// admin/admins.php

// SECURITY: Only Super Admins can access this page
if ($_SESSION['admin_role'] !== 'super_admin') {
    echo "<div class='p-10 text-center bg-red-900/20 border border-red-500/50 rounded-2xl m-6 shadow-2xl'>
            <div class='w-20 h-20 bg-red-500/10 rounded-full flex items-center justify-center mx-auto mb-4 border border-red-500/30 shadow-[0_0_15px_rgba(239,68,68,0.3)]'>
                <i class='fas fa-lock text-4xl text-red-500'></i>
            </div>
            <h2 class='text-2xl font-bold text-white mb-2'>Access Denied</h2>
            <p class='text-slate-400'>Only Super Administrators have permission to manage staff accounts and access levels.</p>
          </div>";
    return;
}

// 1. Handle Add New Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
    $new_user = trim($_POST['username']);
    $new_pass = $_POST['password'];
    $role = $_POST['role'];

    // Validation
    $check = $pdo->prepare("SELECT id FROM adm_user WHERE username = ?");
    $check->execute([$new_user]);
    
    if (strlen($new_pass) < 8) {
        $error = "Master key must be at least 8 characters for security compliance.";
    } elseif (!preg_match("/^[a-zA-Z0-9_]+$/", $new_user)) {
        $error = "Admin ID can only contain letters, numbers, and underscores.";
    } elseif ($check->rowCount() > 0) {
        $error = "Admin ID '{$new_user}' is already registered in the system.";
    } else {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("INSERT INTO adm_user (username, password, role) VALUES (?, ?, ?)");
            $stmt->execute([$new_user, $hashed, $role]);
            
            // Optional: Log this action if you have an admin_logs table
            // log_admin_action($_SESSION['admin_id'], 'CREATE_STAFF', "Created $role account for $new_user");
            
            redirect(admin_url('admins', ['success' => 'created']));
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// 2. Handle Update Role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $target_id = (int)$_POST['target_id'];
    $new_role = $_POST['new_role'];
    
    // Prevent accidentally demoting yourself to support if you are the only super admin
    if ($target_id === $_SESSION['admin_id'] && $new_role !== 'super_admin') {
        $super_admin_count = $pdo->query("SELECT COUNT(*) FROM adm_user WHERE role = 'super_admin'")->fetchColumn();
        if ($super_admin_count <= 1) {
            $error = "Cannot demote the last remaining Super Admin.";
        }
    }
    
    if (!isset($error)) {
        $stmt = $pdo->prepare("UPDATE adm_user SET role = ? WHERE id = ?");
        $stmt->execute([$new_role, $target_id]);
        redirect(admin_url('admins', ['success' => 'updated']));
    }
}

// 3. Handle Delete Admin
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Crucial Security Checks
    if ($id === $_SESSION['admin_id']) {
        $error = "Critical Error: You cannot terminate your own active session.";
    } else {
        // Prevent deleting the very last super admin
        $target_role = $pdo->prepare("SELECT role FROM adm_user WHERE id = ?");
        $target_role->execute([$id]);
        $role_to_delete = $target_role->fetchColumn();
        
        $super_admin_count = $pdo->query("SELECT COUNT(*) FROM adm_user WHERE role = 'super_admin'")->fetchColumn();
        
        if ($role_to_delete === 'super_admin' && $super_admin_count <= 1) {
            $error = "System safeguard: Cannot delete the last Super Admin.";
        } else {
            $pdo->prepare("DELETE FROM adm_user WHERE id = ?")->execute([$id]);
            redirect(admin_url('admins', ['success' => 'deleted']));
        }
    }
}

// 4. Fetch All Admins with Stats
$admins = $pdo->query("
    SELECT id, username, role, last_login, created_at,
    (SELECT COUNT(*) FROM order_messages WHERE sender_type = 'admin' AND message LIKE CONCAT('%', username, '%')) as approx_interactions
    FROM adm_user 
    ORDER BY role DESC, created_at DESC
")->fetchAll();

// Stats calculation
$total_staff = count($admins);
$online_recently = 0;
$time_threshold = date('Y-m-d H:i:s', strtotime('-24 hours'));
foreach($admins as $a) {
    if($a['last_login'] && $a['last_login'] > $time_threshold) $online_recently++;
}
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-white tracking-tight flex items-center gap-3">
        Staff Directory <span class="h-2 w-2 rounded-full bg-[#00f0ff] shadow-[0_0_10px_#00f0ff] animate-pulse"></span>
    </h1>
    <p class="text-slate-400 text-sm mt-1">Deploy, monitor, and manage system administrators.</p>
</div>

<!-- Status Messages -->
<?php if(isset($_GET['success'])): ?>
    <div class="bg-green-500/10 border border-green-500/30 text-green-400 p-4 rounded-xl mb-6 flex items-center gap-3 animate-fade-in-down shadow-[0_0_15px_rgba(34,197,94,0.1)]">
        <i class="fas fa-shield-check text-lg"></i>
        <span>
            <?php 
                if($_GET['success'] == 'created') echo "New staff operative deployed successfully.";
                if($_GET['success'] == 'deleted') echo "Staff account permanently terminated.";
                if($_GET['success'] == 'updated') echo "Security clearance level updated.";
            ?>
        </span>
    </div>
<?php endif; ?>

<?php if(isset($error)): ?>
    <div class="bg-red-500/10 border border-red-500/30 text-red-400 p-4 rounded-xl mb-6 flex items-center gap-3 animate-pulse shadow-[0_0_15px_rgba(239,68,68,0.1)]">
        <i class="fas fa-engine-warning text-lg"></i> <?php echo $error; ?>
    </div>
<?php endif; ?>

<!-- Quick Telemetry -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-slate-800/50 p-4 rounded-xl border border-slate-700/50 backdrop-blur">
        <p class="text-[10px] text-slate-500 uppercase font-bold tracking-widest mb-1">Total Personnel</p>
        <p class="text-2xl font-mono text-white font-bold"><?php echo str_pad($total_staff, 2, '0', STR_PAD_LEFT); ?></p>
    </div>
    <div class="bg-slate-800/50 p-4 rounded-xl border border-slate-700/50 backdrop-blur">
        <p class="text-[10px] text-slate-500 uppercase font-bold tracking-widest mb-1">Active (24h)</p>
        <p class="text-2xl font-mono text-[#00f0ff] font-bold"><?php echo str_pad($online_recently, 2, '0', STR_PAD_LEFT); ?></p>
    </div>
    <div class="bg-slate-800/50 p-4 rounded-xl border border-slate-700/50 backdrop-blur">
        <p class="text-[10px] text-slate-500 uppercase font-bold tracking-widest mb-1">System Load</p>
        <p class="text-2xl font-mono text-green-400 font-bold">Stable</p>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
    
    <!-- Initialize Staff Panel -->
    <div class="xl:col-span-1">
        <div class="bg-slate-900/80 p-6 rounded-2xl border border-[#00f0ff]/20 shadow-[0_0_20px_rgba(0,240,255,0.05)] relative overflow-hidden group hover:border-[#00f0ff]/40 transition-all duration-300 h-full">
            
            <!-- Neon Background Effect -->
            <div class="absolute -right-20 -top-20 w-48 h-48 bg-[#00f0ff]/5 rounded-full blur-3xl pointer-events-none group-hover:bg-[#00f0ff]/10 transition-colors duration-500"></div>
            
            <h3 class="font-bold text-white mb-6 flex items-center gap-2 relative z-10 border-b border-slate-700/50 pb-3">
                <i class="fas fa-user-astronaut text-[#00f0ff]"></i> Initialize New Operative
            </h3>
            
            <form method="POST" class="space-y-5 relative z-10">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Operative ID</label>
                    <div class="relative">
                        <i class="fas fa-fingerprint absolute left-3 top-3 text-slate-500 text-sm"></i>
                        <input type="text" name="username" placeholder="e.g. agent_smith" required 
                               class="w-full bg-slate-800/50 border border-slate-600 rounded-xl py-2.5 pl-10 pr-3 text-white text-sm focus:border-[#00f0ff] focus:ring-1 focus:ring-[#00f0ff] outline-none shadow-inner transition-all placeholder-slate-600">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Clearance Level (Role)</label>
                    <div class="relative">
                        <i class="fas fa-id-badge absolute left-3 top-3 text-slate-500 text-sm"></i>
                        <select name="role" required class="w-full bg-slate-800/50 border border-slate-600 rounded-xl py-2.5 pl-10 pr-3 text-white text-sm focus:border-[#00f0ff] focus:ring-1 focus:ring-[#00f0ff] outline-none shadow-inner transition-all appearance-none cursor-pointer">
                            <option value="support">Support Agent (Orders & Chat Only)</option>
                            <option value="super_admin">Super Admin (Full System Access)</option>
                        </select>
                        <i class="fas fa-chevron-down absolute right-3 top-3.5 text-slate-500 pointer-events-none text-xs"></i>
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Initial Master Key</label>
                    <div class="relative">
                        <i class="fas fa-key absolute left-3 top-3 text-slate-500 text-sm"></i>
                        <input type="password" name="password" placeholder="Min 8 characters" required 
                               class="w-full bg-slate-800/50 border border-slate-600 rounded-xl py-2.5 pl-10 pr-3 text-white text-sm focus:border-[#00f0ff] focus:ring-1 focus:ring-[#00f0ff] outline-none shadow-inner transition-all placeholder-slate-600">
                    </div>
                    <p class="text-[10px] text-slate-500 mt-2 ml-1 flex items-center gap-1"><i class="fas fa-info-circle"></i> They can change this later in Settings.</p>
                </div>

                <div class="pt-4 mt-2">
                    <button type="submit" name="add_admin" class="w-full bg-gradient-to-r from-blue-600 to-[#00f0ff] hover:from-blue-500 hover:to-[#00f0ff] text-slate-900 font-bold py-3 rounded-xl shadow-[0_0_15px_rgba(0,240,255,0.25)] transition transform active:scale-[0.98] text-sm flex justify-center items-center gap-2">
                        <i class="fas fa-satellite-dish"></i> Deploy Account
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Active Roster -->
    <div class="xl:col-span-2">
        <div class="bg-slate-900/80 backdrop-blur border border-slate-700 rounded-2xl overflow-hidden shadow-xl flex flex-col h-full">
            <div class="p-5 border-b border-slate-700/80 flex justify-between items-center bg-slate-800/40">
                <h3 class="font-bold text-slate-200 flex items-center gap-2">
                    <i class="fas fa-users-cog text-slate-400"></i> Active Roster
                </h3>
            </div>
            
            <div class="overflow-x-auto flex-grow custom-scrollbar">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-900/60 text-slate-400 uppercase text-[10px] font-bold tracking-wider sticky top-0 z-20">
                        <tr>
                            <th class="p-4 pl-6">Operative</th>
                            <th class="p-4">Clearance</th>
                            <th class="p-4 text-center">Status</th>
                            <th class="p-4 text-right pr-6">Commands</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        <?php foreach($admins as $a): 
                            $is_self = ($a['id'] === $_SESSION['admin_id']);
                            $is_super = ($a['role'] === 'super_admin');
                            
                            // Calculate active status (online within last 24h)
                            $is_online = false;
                            $last_login_text = 'Never logged in';
                            if($a['last_login']) {
                                $last_time = strtotime($a['last_login']);
                                $is_online = (time() - $last_time) < 86400; // 24 hours
                                $last_login_text = date('M d, H:i', $last_time);
                            }
                        ?>
                            <tr class="hover:bg-slate-800/40 transition-colors group <?php echo $is_self ? 'bg-blue-900/5' : ''; ?>">
                                
                                <!-- Identity -->
                                <td class="p-4 pl-6">
                                    <div class="flex items-center gap-3">
                                        <div class="relative">
                                            <div class="w-10 h-10 rounded-xl bg-slate-800 border <?php echo $is_super ? 'border-[#00f0ff]/40 text-[#00f0ff]' : 'border-slate-600 text-slate-400'; ?> flex items-center justify-center text-sm font-bold shadow-inner">
                                                <?php echo strtoupper(substr($a['username'], 0, 2)); ?>
                                            </div>
                                            <!-- Online Indicator Dot -->
                                            <?php if($is_online): ?>
                                                <div class="absolute -bottom-1 -right-1 w-3.5 h-3.5 bg-green-500 border-2 border-slate-900 rounded-full z-10"></div>
                                            <?php else: ?>
                                                <div class="absolute -bottom-1 -right-1 w-3.5 h-3.5 bg-slate-500 border-2 border-slate-900 rounded-full z-10"></div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="font-bold text-white flex items-center gap-2 text-sm">
                                                <?php echo htmlspecialchars($a['username']); ?>
                                                <?php if($is_self): ?>
                                                    <span class="text-[9px] bg-blue-500/20 text-blue-400 px-1.5 py-0.5 rounded border border-blue-500/30 uppercase tracking-wide">You</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-[10px] text-slate-500 font-mono mt-0.5">Deployed: <?php echo date('Y-m-d', strtotime($a['created_at'])); ?></div>
                                        </div>
                                    </div>
                                </td>
                                
                                <!-- Clearance Level (Editable via form if not self) -->
                                <td class="p-4 align-middle">
                                    <?php if($is_self): ?>
                                        <!-- Self Role is static display -->
                                        <span class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-md border 
                                            <?php echo $is_super ? 'bg-[#00f0ff]/10 text-[#00f0ff] border-[#00f0ff]/20' : 'bg-slate-800 text-slate-300 border-slate-600'; ?>">
                                            <i class="fas <?php echo $is_super ? 'fa-star' : 'fa-headset'; ?>"></i>
                                            <?php echo $is_super ? 'Super Admin' : 'Support'; ?>
                                        </span>
                                    <?php else: ?>
                                        <!-- Inline Form to change role -->
                                        <form method="POST" class="flex items-center gap-2 m-0" onsubmit="return confirm('Confirm clearance level change for <?php echo htmlspecialchars($a['username']); ?>?');">
                                            <input type="hidden" name="target_id" value="<?php echo $a['id']; ?>">
                                            <select name="new_role" class="bg-slate-900 border border-slate-700 text-[10px] font-bold uppercase tracking-wider px-2 py-1 rounded text-slate-300 focus:border-[#00f0ff] outline-none cursor-pointer appearance-none">
                                                <option value="super_admin" <?php echo $is_super ? 'selected' : ''; ?>>⭐ Super</option>
                                                <option value="support" <?php echo !$is_super ? 'selected' : ''; ?>>🎧 Support</option>
                                            </select>
                                            <button type="submit" name="update_role" class="text-slate-500 hover:text-[#00f0ff] transition p-1 opacity-0 group-hover:opacity-100" title="Save Clearance">
                                                <i class="fas fa-save text-xs"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>

                                <!-- Status / Activity -->
                                <td class="p-4 text-center">
                                    <div class="flex flex-col items-center">
                                        <?php if($is_online): ?>
                                            <span class="text-xs text-green-400 font-medium flex items-center gap-1">
                                                <span class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse"></span> Active
                                            </span>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-500 font-medium">Offline</span>
                                        <?php endif; ?>
                                        <span class="text-[10px] text-slate-600 mt-1 whitespace-nowrap"><?php echo $last_login_text; ?></span>
                                    </div>
                                </td>

                                <!-- Action Commands -->
                                <td class="p-4 text-right pr-6 align-middle">
                                    <?php if(!$is_self): ?>
                                        <a href="<?php echo admin_url('admins', ['delete' => $a['id']]); ?>" 
                                           class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-900/50 border border-slate-700 text-slate-500 hover:text-red-500 hover:border-red-500/50 hover:bg-red-500/10 transition-all shadow-sm"
                                           onclick="return confirm('CRITICAL WARNING: Permanently terminate operative \'<?php echo htmlspecialchars($a['username']); ?>\'? This action cannot be undone.')"
                                           title="Terminate Account">
                                            <i class="fas fa-power-off text-xs"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-800 border border-slate-700 text-slate-600 cursor-not-allowed" title="Current Active Session">
                                            <i class="fas fa-lock text-xs"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="p-3 border-t border-slate-700/50 bg-slate-900/50 text-center text-[10px] text-slate-500 uppercase tracking-widest font-bold">
                End of Roster
            </div>
        </div>
    </div>
</div>