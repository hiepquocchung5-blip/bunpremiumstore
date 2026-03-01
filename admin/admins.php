<?php
// admin/admins.php

// SECURITY: Only Super Admins can access this page
if ($_SESSION['admin_role'] !== 'super_admin') {
    echo "<div class='p-10 text-center bg-red-900/20 border border-red-500/50 rounded-2xl m-6 shadow-2xl'>
            <div class='w-20 h-20 bg-red-500/10 rounded-full flex items-center justify-center mx-auto mb-4 border border-red-500/30'>
                <i class='fas fa-lock text-4xl text-red-500'></i>
            </div>
            <h2 class='text-2xl font-bold text-white mb-2'>Access Denied</h2>
            <p class='text-slate-400'>Only Super Administrators have permission to manage staff accounts.</p>
          </div>";
    return;
}

// 1. Handle Add New Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
    $new_user = trim($_POST['username']);
    $new_pass = $_POST['password'];
    $role = $_POST['role'];

    $check = $pdo->prepare("SELECT id FROM adm_user WHERE username = ?");
    $check->execute([$new_user]);
    
    if (strlen($new_pass) < 8) {
        $error = "Master key must be at least 8 characters.";
    } elseif ($check->rowCount() > 0) {
        $error = "Admin ID already exists.";
    } else {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO adm_user (username, password, role) VALUES (?, ?, ?)");
        if ($stmt->execute([$new_user, $hashed, $role])) {
            redirect(admin_url('admins', ['success' => 1]));
        } else {
            $error = "Database error occurred.";
        }
    }
}

// 2. Handle Delete Admin
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id !== $_SESSION['admin_id']) { // Prevent self-deletion
        $pdo->prepare("DELETE FROM adm_user WHERE id = ?")->execute([$id]);
        redirect(admin_url('admins', ['deleted' => 1]));
    } else {
        $error = "You cannot delete your own account.";
    }
}

// 3. Fetch All Admins
$admins = $pdo->query("SELECT * FROM adm_user ORDER BY created_at DESC")->fetchAll();
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-3xl font-bold text-white tracking-tight">Staff & Admins</h1>
        <p class="text-slate-400 text-sm mt-1">Manage system access and privileges.</p>
    </div>
</div>

<?php if(isset($_GET['success'])): ?>
    <div class="bg-green-500/20 text-green-400 p-4 rounded-xl border border-green-500/50 mb-6 flex items-center gap-3 animate-fade-in-up">
        <i class="fas fa-check-circle"></i> New staff account created successfully.
    </div>
<?php endif; ?>

<?php if(isset($_GET['deleted'])): ?>
    <div class="bg-red-500/20 text-red-400 p-4 rounded-xl border border-red-500/50 mb-6 flex items-center gap-3 animate-fade-in-up">
        <i class="fas fa-trash-alt"></i> Staff account permanently removed.
    </div>
<?php endif; ?>

<?php if(isset($error)): ?>
    <div class="bg-red-500/20 text-red-400 p-4 rounded-xl border border-red-500/50 mb-6 flex items-center gap-3">
        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Add Admin Form -->
    <div class="lg:col-span-1">
        <div class="bg-slate-800 p-6 rounded-2xl border border-slate-700 shadow-xl relative overflow-hidden group">
            <!-- Neon Accent -->
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-600 to-[#00f0ff]"></div>
            
            <h3 class="font-bold text-white mb-6 flex items-center gap-2">
                <i class="fas fa-user-plus text-[#00f0ff]"></i> Initialize New Staff
            </h3>
            
            <form method="POST" class="space-y-5">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Admin ID</label>
                    <div class="relative">
                        <i class="fas fa-fingerprint absolute left-3 top-3 text-slate-500"></i>
                        <input type="text" name="username" placeholder="e.g. support_john" required class="w-full bg-slate-900 border border-slate-600 rounded-lg py-2.5 pl-9 pr-3 text-white text-sm focus:border-[#00f0ff] outline-none shadow-inner transition">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Access Role</label>
                    <div class="relative">
                        <i class="fas fa-shield-alt absolute left-3 top-3 text-slate-500"></i>
                        <select name="role" required class="w-full bg-slate-900 border border-slate-600 rounded-lg py-2.5 pl-9 pr-3 text-white text-sm focus:border-[#00f0ff] outline-none shadow-inner transition appearance-none">
                            <option value="support">Support Agent (Orders & Chat)</option>
                            <option value="super_admin">Super Admin (Full Access)</option>
                        </select>
                        <i class="fas fa-chevron-down absolute right-3 top-3 text-slate-500 pointer-events-none text-xs"></i>
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Master Key</label>
                    <div class="relative">
                        <i class="fas fa-key absolute left-3 top-3 text-slate-500"></i>
                        <input type="password" name="password" placeholder="Min 8 characters" required class="w-full bg-slate-900 border border-slate-600 rounded-lg py-2.5 pl-9 pr-3 text-white text-sm focus:border-[#00f0ff] outline-none shadow-inner transition">
                    </div>
                </div>

                <button type="submit" name="add_admin" class="w-full bg-gradient-to-r from-blue-600 to-[#00f0ff] hover:from-blue-500 hover:to-[#00f0ff] text-slate-900 font-bold py-3 rounded-lg shadow-[0_0_15px_rgba(0,240,255,0.3)] transition transform active:scale-[0.98] text-sm flex justify-center items-center gap-2 mt-2">
                    <i class="fas fa-satellite-dish"></i> Deploy Account
                </button>
            </form>
        </div>
    </div>

    <!-- Admin Roster -->
    <div class="lg:col-span-2">
        <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden shadow-xl">
            <div class="p-5 border-b border-slate-700 flex justify-between items-center bg-slate-800/50 backdrop-blur">
                <h3 class="font-bold text-slate-200">Active Roster</h3>
                <span class="bg-slate-900 px-3 py-1 rounded-full text-xs text-slate-400 border border-slate-700"><?php echo count($admins); ?> Accounts</span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-900/80 text-slate-400 uppercase text-[10px] font-bold tracking-wider">
                        <tr>
                            <th class="p-4 pl-6">Identifier</th>
                            <th class="p-4">Privilege Level</th>
                            <th class="p-4 text-right">Last Login</th>
                            <th class="p-4 text-right pr-6">Command</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        <?php foreach($admins as $a): ?>
                            <tr class="hover:bg-slate-700/30 transition group">
                                <td class="p-4 pl-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-slate-900 border border-slate-600 flex items-center justify-center text-xs font-bold <?php echo $a['role'] == 'super_admin' ? 'text-[#00f0ff] border-[#00f0ff]/30 shadow-[0_0_10px_rgba(0,240,255,0.1)]' : 'text-slate-400'; ?>">
                                            <?php echo strtoupper(substr($a['username'], 0, 2)); ?>
                                        </div>
                                        <div>
                                            <div class="font-bold text-white flex items-center gap-2">
                                                <?php echo htmlspecialchars($a['username']); ?>
                                                <?php if($a['id'] == $_SESSION['admin_id']): ?>
                                                    <span class="text-[9px] bg-green-500/20 text-green-400 px-1.5 py-0.5 rounded border border-green-500/30">YOU</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-[10px] text-slate-500">Joined <?php echo date('M Y', strtotime($a['created_at'])); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <?php if($a['role'] == 'super_admin'): ?>
                                        <span class="text-xs text-[#00f0ff] bg-[#00f0ff]/10 px-2.5 py-1 rounded border border-[#00f0ff]/20 font-bold uppercase tracking-wider flex items-center gap-1 w-max">
                                            <i class="fas fa-star text-[10px]"></i> Super Admin
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-300 bg-slate-700 px-2.5 py-1 rounded border border-slate-600 font-bold uppercase tracking-wider flex items-center gap-1 w-max">
                                            <i class="fas fa-headset text-[10px]"></i> Support
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 text-right">
                                    <?php if($a['last_login']): ?>
                                        <span class="text-slate-300 text-xs block"><?php echo date('M d, H:i', strtotime($a['last_login'])); ?></span>
                                        <span class="text-[10px] text-green-500 mt-0.5 block animate-pulse">Online recently</span>
                                    <?php else: ?>
                                        <span class="text-slate-500 text-xs italic">Never logged in</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 text-right pr-6">
                                    <?php if($a['id'] !== $_SESSION['admin_id']): ?>
                                        <a href="<?php echo admin_url('admins', ['delete' => $a['id']]); ?>" 
                                           class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-900 border border-slate-700 text-slate-500 hover:text-red-500 hover:border-red-500/50 hover:bg-red-500/10 transition shadow-sm"
                                           onclick="return confirm('WARNING: Terminate this admin account completely?')"
                                           title="Terminate Account">
                                            <i class="fas fa-power-off text-xs"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-900 border border-slate-700 text-slate-600 cursor-not-allowed" title="Cannot terminate active session">
                                            <i class="fas fa-lock text-xs"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>