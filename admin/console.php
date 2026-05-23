<?php
// admin/console.php
// PRODUCTION v1.0 - Developer Blacklist & Identity Ban Matrix

// SECURITY: Strict Super Admin Access Only
if ($_SESSION['admin_role'] !== 'super_admin') {
    echo "<div class='p-10 text-center bg-red-900/20 border border-red-500/50 rounded-2xl m-6 shadow-2xl'>
            <div class='w-20 h-20 bg-red-500/10 rounded-full flex items-center justify-center mx-auto mb-4 border border-red-500/30 shadow-[0_0_15px_rgba(239,68,68,0.3)]'>
                <i class='fas fa-lock text-4xl text-red-500'></i>
            </div>
            <h2 class='text-2xl font-bold text-white mb-2'>Maximum Security Clearance Required</h2>
            <p class='text-slate-400'>Only Master Developers have permission to access the Blacklist Console.</p>
          </div>";
    return;
}

// Ensure the ban columns exist in the database, if not, create them instantly to prevent crashes
try {
    $pdo->query("SELECT is_banned, ban_reason FROM users LIMIT 1");
} catch (Exception $e) {
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_banned TINYINT DEFAULT 0 AFTER is_verified");
        $pdo->exec("ALTER TABLE users ADD COLUMN ban_reason VARCHAR(255) DEFAULT NULL AFTER is_banned");
        $system_msg = "Database patched: Blacklist matrix initialized.";
    } catch (Exception $ex) {
        $error = "Critical Matrix Error: Unable to inject blacklist columns. Ensure database permissions.";
    }
}

// 1. Handle Ban Execution (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute_ban'])) {
    $target_id = (int)$_POST['user_id'];
    $reason = trim($_POST['ban_reason']) ?: "Violation of network protocols. Access permanently restricted.";
    
    if ($target_id > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET is_banned = 1, ban_reason = ? WHERE id = ?");
            $stmt->execute([$reason, $target_id]);
            $success = "User identity successfully blacklisted and locked out of the matrix.";
        } catch (Exception $e) {
            $error = "Ban execution failed: " . $e->getMessage();
        }
    }
}

// 2. Handle Lift Ban (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lift_ban'])) {
    $target_id = (int)$_POST['user_id'];
    
    if ($target_id > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET is_banned = 0, ban_reason = NULL WHERE id = ?");
            $stmt->execute([$target_id]);
            $success = "User identity restored. Ban lifted.";
        } catch (Exception $e) {
            $error = "Unban execution failed: " . $e->getMessage();
        }
    }
}

// 3. Search Users Logic
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$users = [];

if ($search_query) {
    $term = "%$search_query%";
    $stmt = $pdo->prepare("SELECT id, username, email, is_banned, ban_reason, created_at FROM users WHERE username LIKE ? OR email LIKE ? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$term, $term]);
    $users = $stmt->fetchAll();
} else {
    // Just fetch recently banned users by default
    $stmt = $pdo->query("SELECT id, username, email, is_banned, ban_reason, created_at FROM users WHERE is_banned = 1 ORDER BY created_at DESC LIMIT 20");
    $users = $stmt->fetchAll();
}

// Analytics
$total_banned = $pdo->query("SELECT COUNT(*) FROM users WHERE is_banned = 1")->fetchColumn() ?: 0;
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-3xl font-black text-red-500 tracking-tight flex items-center gap-3 drop-shadow-[0_0_10px_rgba(239,68,68,0.5)]">
            Developer Ban Console <i class="fas fa-radiation animate-pulse"></i>
        </h1>
        <p class="text-slate-400 text-sm mt-1">Execute permanent identity blacklists to protect the matrix.</p>
    </div>
</div>

<?php if(isset($system_msg)): ?>
    <div class="bg-blue-500/10 border border-blue-500/30 text-blue-400 p-4 rounded-xl mb-6 flex items-center gap-3 shadow-lg">
        <i class="fas fa-info-circle text-lg"></i> <?php echo $system_msg; ?>
    </div>
<?php endif; ?>

<?php if(isset($success)): ?>
    <div class="bg-green-500/10 border border-green-500/30 text-green-400 p-4 rounded-xl mb-6 flex items-center gap-3 animate-fade-in-down shadow-[0_0_15px_rgba(34,197,94,0.2)]">
        <i class="fas fa-check-circle text-lg"></i> <?php echo $success; ?>
    </div>
<?php endif; ?>

<?php if(isset($error)): ?>
    <div class="bg-red-500/10 border border-red-500/30 text-red-400 p-4 rounded-xl mb-6 flex items-center gap-3 animate-pulse shadow-[0_0_15px_rgba(239,68,68,0.2)]">
        <i class="fas fa-exclamation-triangle text-lg"></i> <?php echo $error; ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- LEFT: Search & Target Selection -->
    <div class="lg:col-span-1">
        <div class="bg-slate-900/80 backdrop-blur border border-red-500/30 p-6 rounded-3xl shadow-[0_0_30px_rgba(239,68,68,0.1)] sticky top-6 relative overflow-hidden group">
            
            <div class="absolute -right-20 -top-20 w-48 h-48 bg-red-500/10 rounded-full blur-3xl pointer-events-none group-hover:bg-red-500/20 transition-all duration-700"></div>

            <h3 class="font-black text-white mb-5 flex items-center gap-2 border-b border-slate-700/50 pb-3 relative z-10">
                <i class="fas fa-crosshairs text-red-500"></i> Target Acquisition
            </h3>

            <!-- Search Form -->
            <form method="GET" class="mb-6 relative z-10">
                <input type="hidden" name="page" value="console">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Search Database</label>
                <div class="relative flex items-center">
                    <i class="fas fa-search absolute left-3 top-3 text-slate-500 text-sm"></i>
                    <input type="text" name="q" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Username or Email" required class="w-full bg-slate-950 border border-slate-600 rounded-lg py-2.5 pl-9 pr-24 text-white text-sm focus:border-red-500 outline-none shadow-inner transition font-mono">
                    <button type="submit" class="absolute right-1 top-1 bottom-1 bg-red-600 hover:bg-red-500 text-white font-bold px-4 rounded text-xs transition uppercase tracking-widest">Find</button>
                </div>
            </form>
            
            <!-- Target Summary -->
            <div class="border-t border-slate-700/50 pt-5 relative z-10">
                <div class="flex items-center gap-4 bg-slate-950/50 border border-red-500/20 p-4 rounded-xl shadow-inner">
                    <div class="w-12 h-12 rounded-xl bg-red-500/10 border border-red-500/30 flex items-center justify-center text-red-500 text-xl font-black shrink-0 shadow-[0_0_15px_rgba(239,68,68,0.3)]">
                        <?php echo $total_banned; ?>
                    </div>
                    <div>
                        <p class="text-white font-bold tracking-wide">Total Blacklisted</p>
                        <p class="text-[10px] text-slate-400 uppercase tracking-widest">Identities permanently blocked</p>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- RIGHT: Results & Execution Table -->
    <div class="lg:col-span-2">
        <div class="bg-slate-900/60 backdrop-blur rounded-3xl border border-slate-700 overflow-hidden shadow-2xl flex flex-col h-full relative">
            
            <div class="p-5 border-b border-slate-700/80 bg-slate-800/40 flex justify-between items-center shrink-0">
                <h3 class="font-bold text-white text-lg flex items-center gap-2"><i class="fas fa-users-slash text-slate-400"></i> Identity Matrix Results</h3>
                <span class="bg-slate-800 border border-slate-600 px-3 py-1 rounded-lg text-xs font-bold text-slate-300"><?php echo count($users); ?> Nodes Found</span>
            </div>
            
            <div class="overflow-x-auto flex-grow custom-scrollbar">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="bg-slate-950/80 text-slate-400 uppercase text-[10px] tracking-widest font-black border-b border-slate-700/50">
                        <tr>
                            <th class="p-5 pl-6">Target Info</th>
                            <th class="p-5 text-center">Status</th>
                            <th class="p-5 text-right pr-6">Action Command</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/80">
                        <?php foreach($users as $u): ?>
                            <tr class="hover:bg-slate-800/40 transition-colors group">
                                
                                <td class="p-5 pl-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded bg-slate-800 flex items-center justify-center text-xs font-bold text-slate-400 border border-slate-700 shadow-inner">
                                            <?php echo strtoupper(substr($u['username'], 0, 2)); ?>
                                        </div>
                                        <div>
                                            <div class="font-bold text-white text-sm group-hover:text-red-400 transition-colors">@<?php echo htmlspecialchars($u['username']); ?></div>
                                            <div class="text-[10px] text-slate-500 font-mono mt-0.5"><?php echo htmlspecialchars($u['email']); ?></div>
                                        </div>
                                    </div>
                                    <?php if($u['is_banned']): ?>
                                        <div class="mt-3 bg-red-900/10 border-l-2 border-red-500 p-2 rounded text-[10px] text-red-300 whitespace-normal">
                                            <strong>Reason:</strong> <?php echo htmlspecialchars($u['ban_reason']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="p-5 text-center align-middle">
                                    <?php if($u['is_banned']): ?>
                                        <span class="inline-flex items-center gap-1.5 bg-red-500/10 text-red-400 border border-red-500/30 px-2.5 py-1 rounded text-[9px] font-black uppercase tracking-widest shadow-[0_0_10px_rgba(239,68,68,0.2)] animate-pulse">
                                            <i class="fas fa-skull-crossbones"></i> Banned
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 bg-green-500/10 text-green-400 border border-green-500/30 px-2.5 py-1 rounded text-[9px] font-black uppercase tracking-widest shadow-[0_0_10px_rgba(34,197,94,0.1)]">
                                            <i class="fas fa-check-circle"></i> Clean
                                        </span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="p-5 text-right pr-6 align-middle">
                                    <?php if($u['is_banned']): ?>
                                        <!-- Unban Form -->
                                        <form method="POST" class="inline-block" onsubmit="return confirm('Restore identity to the matrix?');">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <button type="submit" name="lift_ban" class="bg-slate-800 hover:bg-green-600 text-green-400 hover:text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all duration-300 border border-green-500/30 hover:border-transparent hover:shadow-[0_0_15px_rgba(34,197,94,0.4)] flex items-center gap-2 ml-auto">
                                                <i class="fas fa-unlock"></i> Lift Ban
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <!-- Ban Action triggers modal to get reason -->
                                        <button type="button" onclick="openBanModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['username']); ?>')" class="bg-slate-800 hover:bg-red-600 text-red-400 hover:text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all duration-300 border border-red-500/30 hover:border-transparent hover:shadow-[0_0_15px_rgba(239,68,68,0.4)] flex items-center gap-2 ml-auto">
                                            <i class="fas fa-gavel"></i> Execute Ban
                                        </button>
                                    <?php endif; ?>
                                </td>
                                
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if(empty($users)): ?>
                            <tr>
                                <td colspan="3" class="p-12 text-center text-slate-500 relative overflow-hidden">
                                    <div class="relative z-10">
                                        <div class="w-16 h-16 bg-slate-900 rounded-full flex items-center justify-center mx-auto mb-4 border border-slate-700 shadow-inner">
                                            <i class="fas fa-search text-2xl text-slate-600"></i>
                                        </div>
                                        <p class="font-bold tracking-tight text-white mb-1">No targets identified.</p>
                                        <p class="text-xs font-mono">Use the search panel to locate specific users.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
        </div>
    </div>
</div>

<!-- ===================================================================================== -->
<!-- BAN EXECUTION MODAL                                                                   -->
<!-- ===================================================================================== -->
<div id="banModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-slate-950/80 backdrop-blur-sm" onclick="closeBanModal()"></div>
    
    <!-- Content -->
    <div class="bg-slate-900 border border-red-500/50 rounded-3xl w-full max-w-md relative z-10 shadow-[0_20px_60px_rgba(239,68,68,0.4)] transform scale-95 opacity-0 transition-all duration-300 overflow-hidden" id="banModalContent">
        
        <div class="absolute top-0 right-0 w-48 h-48 bg-red-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="p-6 border-b border-slate-700/80 bg-red-900/20">
            <h3 class="font-black text-red-500 flex items-center gap-2 tracking-tight text-xl">
                <i class="fas fa-exclamation-triangle animate-pulse"></i> Terminate Identity
            </h3>
        </div>

        <form method="POST" class="p-6 relative z-10 space-y-5">
            <input type="hidden" name="user_id" id="ban_user_id" value="">
            
            <p class="text-sm text-slate-300 font-medium">You are about to permanently blacklist operative: <strong class="text-white bg-slate-800 px-2 py-0.5 rounded font-mono border border-slate-600" id="ban_username">@username</strong></p>
            
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Reason for Termination</label>
                <textarea name="ban_reason" rows="3" required placeholder="State exact policy violation..." 
                          class="w-full bg-slate-950 border border-slate-600 rounded-xl p-4 text-red-400 text-sm focus:border-red-500 outline-none shadow-inner transition-colors resize-none"></textarea>
            </div>

            <div class="pt-2 flex gap-3">
                <button type="button" onclick="closeBanModal()" class="flex-1 bg-slate-800 hover:bg-slate-700 text-white font-bold py-3.5 rounded-xl border border-slate-600 transition text-sm">Abort</button>
                <button type="submit" name="execute_ban" class="flex-1 bg-red-600 hover:bg-red-500 text-white font-black py-3.5 rounded-xl shadow-[0_0_20px_rgba(239,68,68,0.4)] transition transform active:scale-95 text-sm uppercase tracking-widest flex justify-center items-center gap-2">
                    <i class="fas fa-gavel"></i> Execute
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openBanModal(id, username) {
        document.getElementById('ban_user_id').value = id;
        document.getElementById('ban_username').innerText = '@' + username;
        
        const modal = document.getElementById('banModal');
        const mContent = document.getElementById('banModalContent');
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        setTimeout(() => {
            mContent.classList.remove('scale-95', 'opacity-0');
            mContent.classList.add('scale-100', 'opacity-100');
        }, 10);
    }

    function closeBanModal() {
        const modal = document.getElementById('banModal');
        const mContent = document.getElementById('banModalContent');
        
        mContent.classList.remove('scale-100', 'opacity-100');
        mContent.classList.add('scale-95', 'opacity-0');
        
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }, 300);
    }
</script>