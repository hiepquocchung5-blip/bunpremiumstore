<?php
// modules/user/agent.php
// PRODUCTION DEPLOYMENT v3.1 - Interactive Payment Nodes & Timer

// Auth Guard
if (!is_logged_in()) redirect('index.php?module=auth&page=login');

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// =====================================================================================
// 1. HANDLE PASS PURCHASE
// =====================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_pass'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) die("Invalid Token");

    $pass_id = (int)$_POST['pass_id'];
    $txn_id = trim($_POST['txn_id']);
    
    // Validate Pass
    $stmt = $pdo->prepare("SELECT * FROM passes WHERE id = ? AND is_active = 1");
    $stmt->execute([$pass_id]);
    $pass = $stmt->fetch();

    if (!$pass) {
        $error = "Invalid or inactive pass selected.";
    } elseif (strlen($txn_id) !== 6) {
        $error = "Transaction ID must be exactly the last 6 digits.";
    } elseif (empty($_FILES['proof']['name'])) {
        $error = "Payment screenshot is required.";
    } else {
        // Handle File Upload
        $target_dir = "uploads/proofs/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
        
        $ext = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($ext, $allowed_ext)) {
            $filename = "pass_" . uniqid() . "." . $ext;
            $target_file = $target_dir . $filename;
            
            if (move_uploaded_file($_FILES['proof']['tmp_name'], $target_file)) {
                try {
                    $form_data = json_encode([
                        'Type' => 'Agent Pass Upgrade',
                        'Pass Tier' => $pass['name'],
                        'Duration' => $pass['duration_days'] . ' Days'
                    ]);

                    // FIX: Clean Architecture - Inserting pass_id, product_id is strictly NULL
                    $sql = "INSERT INTO orders (user_id, product_id, pass_id, email_delivery_type, delivery_email, form_data, transaction_last_6, proof_image_path, total_price_paid, status) 
                            VALUES (?, NULL, ?, 'own', ?, ?, ?, ?, ?, 'pending')";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$user_id, $pass['id'], $_SESSION['user_email'], $form_data, $txn_id, $target_file, $pass['price']]);
                    
                    $order_id = $pdo->lastInsertId();
                    
                    // Notify Admin
                    if (function_exists('send_telegram_alert')) {
                        send_telegram_alert($order_id, "Agent Pass Upgrade: " . $pass['name'], $pass['price'], $_SESSION['user_name']);
                    }
                    
                    $success = "Upgrade sequence initiated! Admin will verify your transfer and authorize access shortly.";
                    
                } catch (PDOException $e) {
                    $error = "System error processing request: " . $e->getMessage();
                }
            } else {
                $error = "Failed to upload proof. Check directory permissions.";
            }
        } else {
            $error = "Invalid file type. Only images allowed.";
        }
    }
}

// =====================================================================================
// 2. FETCH DATA
// =====================================================================================

// Fetch Available Passes
$stmt = $pdo->query("SELECT * FROM passes WHERE is_active = 1 ORDER BY price ASC");
$passes = $stmt->fetchAll();

// Fetch Current User Pass
$stmt = $pdo->prepare("
    SELECT up.*, p.name, p.discount_percent, p.description 
    FROM user_passes up 
    JOIN passes p ON up.pass_id = p.id 
    WHERE up.user_id = ? AND up.status = 'active' AND up.expires_at > NOW()
");
$stmt->execute([$user_id]);
$active_pass = $stmt->fetch();

// Fetch Payment Methods for Modal
$payment_methods = $pdo->query("SELECT * FROM payment_methods WHERE is_active = 1")->fetchAll();

// Fetch User Data for Referral Code & Wallet
$stmt = $pdo->prepare("SELECT referral_code, wallet_balance FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();

$ref_link = $user_data['referral_code'] ? BASE_URL . "index.php?module=auth&page=register&ref=" . $user_data['referral_code'] : null;

// Calculate Estimated Total Savings
$total_orders_stmt = $pdo->prepare("SELECT SUM(price - total_price_paid) FROM orders o JOIN products p ON o.product_id = p.id WHERE o.user_id = ? AND o.status = 'active'");
$total_orders_stmt->execute([$user_id]);
$total_savings = $total_orders_stmt->fetchColumn() ?: 0;

?>

<div class="max-w-7xl mx-auto animate-fade-in-down px-4 sm:px-6 lg:px-8 py-8">
    
    <!-- Dynamic Header -->
    <div class="flex flex-col md:flex-row md:items-end justify-between mb-10 gap-6 relative z-10">
        <div>
            <h1 class="text-3xl md:text-5xl font-black text-transparent bg-clip-text bg-gradient-to-r from-yellow-200 via-yellow-400 to-yellow-600 drop-shadow-sm tracking-tight mb-2">
                Agent Command Center
            </h1>
            <p class="text-slate-400 text-sm max-w-2xl leading-relaxed">
                <?php echo $active_pass ? "Manage your reseller metrics, access your network link, and view active privileges." : "Unlock wholesale pricing on all digital products. Start your own reselling business today with our premium membership tiers."; ?>
            </p>
        </div>
        
        <?php if($active_pass): ?>
            <div class="bg-yellow-500/10 border border-yellow-500/30 px-5 py-3 rounded-2xl flex items-center gap-4 shadow-[0_0_20px_rgba(234,179,8,0.15)] relative overflow-hidden shrink-0">
                <div class="absolute right-0 top-0 w-16 h-16 bg-yellow-500/20 rounded-full blur-xl pointer-events-none"></div>
                <div class="w-12 h-12 bg-gradient-to-br from-yellow-400 to-yellow-600 rounded-full flex items-center justify-center text-slate-900 text-xl font-black shadow-lg relative z-10 shrink-0">
                    <?php echo $active_pass['discount_percent']; ?>%
                </div>
                <div class="relative z-10">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Active Tier</p>
                    <h3 class="text-lg font-black text-yellow-400 leading-none"><?php echo htmlspecialchars($active_pass['name']); ?></h3>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Notifications -->
    <?php if($success): ?>
        <div class="max-w-3xl mb-8 bg-green-500/10 border border-green-500/30 text-green-400 p-5 md:p-6 rounded-2xl flex items-center gap-4 shadow-[0_0_20px_rgba(34,197,94,0.15)] relative z-10">
            <div class="w-10 h-10 md:w-12 md:h-12 bg-green-500/20 rounded-full flex items-center justify-center text-green-400 border border-green-500/50 shrink-0">
                <i class="fas fa-check text-lg md:text-xl"></i>
            </div>
            <div>
                <h4 class="font-bold text-base md:text-lg">Deployment Authorized!</h4>
                <p class="text-xs md:text-sm opacity-90 mt-1">Please check your <a href="index.php?module=user&page=orders" class="underline font-bold text-white hover:text-green-300">Orders Page</a> to track the status of your upgrade.</p>
            </div>
        </div>
    <?php endif; ?>

    <?php if($error): ?>
        <div class="max-w-3xl mb-8 bg-red-500/10 border border-red-500/30 text-red-400 p-4 rounded-xl flex items-start gap-3 animate-pulse relative z-10">
            <i class="fas fa-exclamation-triangle text-lg shrink-0 mt-0.5"></i>
            <span class="text-sm font-medium leading-relaxed"><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <!-- Agent Dashboard Area (Only visible if active) -->
    <?php if($active_pass): ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12 relative z-10">
        
        <!-- Referral Link Generator -->
        <div class="md:col-span-2 bg-slate-900/80 backdrop-blur-xl border border-slate-700/50 rounded-3xl p-6 md:p-8 flex flex-col justify-center relative overflow-hidden group shadow-[0_10px_30px_rgba(0,0,0,0.5)]">
            <div class="absolute -right-20 -top-20 w-64 h-64 bg-[#00f0ff]/5 rounded-full blur-3xl pointer-events-none group-hover:bg-[#00f0ff]/10 transition duration-700"></div>
            
            <h3 class="text-lg md:text-xl font-black text-white mb-2 flex items-center gap-3">
                <i class="fas fa-network-wired text-[#00f0ff]"></i> Expand Your Network
            </h3>
            <p class="text-sm text-slate-400 mb-6">Distribute your unique referral link to clients. Earn passive commissions on their deployments.</p>
            
            <?php if($ref_link): ?>
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1 group/input">
                    <i class="fas fa-link absolute left-4 top-1/2 transform -translate-y-1/2 text-slate-500 group-focus-within/input:text-[#00f0ff] transition-colors"></i>
                    <input type="text" value="<?php echo $ref_link; ?>" readonly class="w-full bg-slate-950 border border-slate-600 rounded-xl py-3.5 pl-10 pr-4 text-sm text-[#00f0ff] focus:border-[#00f0ff] outline-none select-all font-mono font-bold shadow-inner transition-colors">
                </div>
                <button onclick="navigator.clipboard.writeText('<?php echo addslashes($ref_link); ?>'); this.innerHTML='<i class=\'fas fa-check\'></i> Copied!'; setTimeout(()=>this.innerHTML='<i class=\'fas fa-copy\'></i> Copy Link', 2000);" class="bg-gradient-to-r from-blue-600 to-[#00f0ff] hover:from-blue-500 hover:to-[#00f0ff] text-slate-900 font-black px-8 py-3.5 rounded-xl transition shadow-[0_0_15px_rgba(0,240,255,0.3)] transform active:scale-95 flex items-center justify-center gap-2 uppercase tracking-widest text-xs shrink-0">
                    <i class="fas fa-copy"></i> Copy Link
                </button>
            </div>
            <?php else: ?>
            <div class="bg-slate-800 p-4 rounded-xl border border-slate-700 text-sm text-slate-400 flex items-center gap-3">
                <i class="fas fa-info-circle text-blue-400"></i>
                <span>Please visit your <a href="index.php?module=user&page=referrals" class="text-[#00f0ff] hover:underline font-bold">Referral Dashboard</a> to generate your unique link.</span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Lifetime Savings -->
        <div class="bg-slate-900/80 backdrop-blur-xl border border-green-500/30 rounded-3xl p-6 md:p-8 flex flex-col items-center justify-center text-center relative overflow-hidden group shadow-[0_10px_30px_rgba(0,0,0,0.5)]">
            <div class="absolute inset-0 bg-green-500/5 group-hover:bg-green-500/10 transition duration-500"></div>
            <i class="fas fa-chart-line text-4xl text-green-400 mb-4 group-hover:scale-110 transition duration-300 drop-shadow-[0_0_10px_rgba(34,197,94,0.5)]"></i>
            <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1 relative z-10">Total Savings</p>
            <h3 class="text-3xl font-black text-white relative z-10"><?php echo number_format($total_savings); ?> <span class="text-green-400 text-lg">Ks</span></h3>
        </div>

    </div>
    <?php endif; ?>

    <!-- Title For Tiers -->
    <div class="flex items-center gap-4 mb-6 relative z-10">
        <h2 class="text-xl font-black text-white uppercase tracking-widest">Available Tiers</h2>
        <div class="h-px bg-slate-700 flex-1"></div>
    </div>

    <!-- Plans Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 xl:gap-8 relative z-10 mb-10">
        
        <!-- Standard / Free Tier Info -->
        <div class="bg-slate-900/80 backdrop-blur-xl p-6 sm:p-8 rounded-3xl border border-slate-700/50 flex flex-col grayscale opacity-80 hover:opacity-100 hover:grayscale-0 transition duration-500 shadow-xl">
            <div class="mb-6">
                <span class="text-[10px] font-bold tracking-widest text-slate-500 uppercase">System Default</span>
                <h3 class="text-2xl font-bold text-white mt-1 tracking-tight">Standard User</h3>
            </div>
            <ul class="space-y-4 mb-8 flex-1">
                <li class="flex items-center gap-3 text-slate-400 text-sm font-medium">
                    <i class="fas fa-times-circle text-slate-600"></i> Retail Prices
                </li>
                <li class="flex items-center gap-3 text-slate-400 text-sm font-medium">
                    <i class="fas fa-check-circle text-[#00f0ff]"></i> Basic Support
                </li>
                <li class="flex items-center gap-3 text-slate-400 text-sm font-medium">
                    <i class="fas fa-check-circle text-[#00f0ff]"></i> Instant Delivery
                </li>
            </ul>
            <button disabled class="w-full py-4 rounded-xl border border-slate-700 bg-slate-800 text-slate-500 font-bold text-sm cursor-not-allowed uppercase tracking-widest">
                Active
            </button>
        </div>

        <!-- Premium Passes -->
        <?php foreach($passes as $pass): ?>
            <?php 
                $isActive = $active_pass && $active_pass['pass_id'] == $pass['id'];
                $isUpgrade = !$active_pass || $active_pass['discount_percent'] < $pass['discount_percent'];
            ?>
            <div class="relative group h-full">
                <!-- Glow Effect for Premium -->
                <div class="absolute -inset-0.5 bg-gradient-to-r from-yellow-600 to-yellow-300 rounded-3xl blur-lg opacity-20 group-hover:opacity-50 transition duration-500"></div>
                
                <div class="relative bg-slate-900/90 backdrop-blur-xl p-6 sm:p-8 rounded-3xl border border-yellow-500/30 flex flex-col h-full shadow-2xl overflow-hidden">
                    
                    <div class="absolute -right-10 -top-10 w-32 h-32 bg-yellow-500/10 rounded-full blur-3xl pointer-events-none"></div>

                    <?php if($isUpgrade && !$isActive): ?>
                        <div class="absolute top-0 right-0 bg-gradient-to-r from-yellow-500 to-yellow-600 text-slate-900 text-[10px] font-black px-4 py-1.5 rounded-bl-2xl uppercase tracking-wider shadow-lg">
                            Upgrade
                        </div>
                    <?php endif; ?>

                    <div class="mb-6 relative z-10">
                        <span class="text-[10px] font-bold tracking-widest text-yellow-500 uppercase flex items-center gap-2">
                            <i class="fas fa-star animate-pulse"></i> Premium Tier
                        </span>
                        <h3 class="text-2xl sm:text-3xl font-black text-white mt-2 tracking-tight leading-tight"><?php echo htmlspecialchars($pass['name']); ?></h3>
                        <div class="mt-3 flex items-baseline gap-1.5 flex-wrap">
                            <span class="text-3xl sm:text-4xl font-black text-transparent bg-clip-text bg-gradient-to-br from-yellow-200 to-yellow-500 drop-shadow-sm tracking-tighter">
                                <?php echo number_format($pass['price']); ?>
                            </span>
                            <span class="text-xs sm:text-sm text-slate-400 font-medium">Ks / <?php echo $pass['duration_days']; ?> Days</span>
                        </div>
                    </div>

                    <div class="space-y-5 mb-8 flex-1 relative z-10">
                        <div class="flex items-center gap-4 bg-slate-800/80 p-3 sm:p-4 rounded-2xl border border-yellow-500/20 shadow-inner group-hover:border-yellow-500/40 transition">
                            <div class="w-12 h-12 rounded-xl bg-yellow-500/10 flex items-center justify-center text-yellow-400 font-black text-xl border border-yellow-500/30 shadow-[0_0_15px_rgba(234,179,8,0.2)] shrink-0 group-hover:scale-110 transition duration-300">
                                <?php echo $pass['discount_percent']; ?>%
                            </div>
                            <div>
                                <p class="text-sm text-white font-bold tracking-wide">Store-wide Discount</p>
                                <p class="text-[10px] sm:text-xs text-slate-400 mt-0.5">Applied automatically to all items</p>
                            </div>
                        </div>
                        
                        <div class="text-xs sm:text-sm text-slate-300 leading-relaxed pl-3 border-l-2 border-slate-700 font-medium">
                            <?php echo nl2br(htmlspecialchars($pass['description'])); ?>
                        </div>
                    </div>

                    <div class="mt-auto relative z-10">
                        <?php if ($isActive): ?>
                            <div class="w-full py-4 rounded-xl bg-green-500/10 border border-green-500/30 text-green-400 font-bold text-center flex flex-col items-center justify-center shadow-inner">
                                <span class="flex items-center gap-2 text-sm"><i class="fas fa-check-circle"></i> Currently Active</span>
                                <span class="text-[10px] font-mono mt-1 opacity-80 uppercase tracking-wider">Expires: <?php echo date('M d, Y', strtotime($active_pass['expires_at'])); ?></span>
                            </div>
                        <?php else: ?>
                            <button onclick="openCheckoutModal(<?php echo $pass['id']; ?>, '<?php echo addslashes($pass['name']); ?>', <?php echo $pass['price']; ?>)" 
                                    class="w-full py-4 rounded-xl bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-400 hover:to-yellow-500 text-slate-900 font-black text-sm uppercase tracking-wider shadow-[0_0_20px_rgba(234,179,8,0.3)] transform hover:-translate-y-1 hover:scale-[1.02] transition-all duration-300 flex items-center justify-center gap-2">
                                <?php echo $active_pass ? 'Upgrade Tier' : 'Acquire Pass'; ?> <i class="fas fa-arrow-right"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ===================================================================================== -->
<!-- PASS CHECKOUT MODAL (Interactive Session Timer & Nodes)                               -->
<!-- ===================================================================================== -->
<div id="checkoutModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-3 sm:p-4">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-slate-950/90 backdrop-blur-md" onclick="closeCheckoutModal()"></div>
    
    <!-- Modal Content -->
    <div class="bg-slate-900 border border-slate-700 rounded-3xl w-full max-w-2xl relative z-10 shadow-[0_20px_60px_rgba(0,0,0,0.8)] flex flex-col transform scale-95 opacity-0 transition-all duration-300 max-h-[95vh]" id="modalContent">
        
        <!-- Sticky Header -->
        <div class="bg-slate-800/90 backdrop-blur-md border-b border-slate-700 p-5 sm:p-6 flex justify-between items-center relative overflow-hidden shrink-0 rounded-t-3xl">
            <div class="absolute inset-0 bg-gradient-to-r from-yellow-500/10 to-transparent pointer-events-none"></div>
            <div class="relative z-10">
                <h3 class="text-lg sm:text-xl font-bold text-white flex items-center gap-2">
                    <i class="fas fa-shopping-cart text-yellow-500"></i> Pass Checkout
                </h3>
                <p class="text-[10px] sm:text-xs text-slate-400 mt-1 font-medium tracking-wide" id="modalPassName">Loading...</p>
            </div>
            
            <div class="flex items-center gap-4 relative z-10">
                 <!-- 5 Minute Session Timer (Hidden initially) -->
                 <div id="sessionTimerContainer" class="hidden bg-red-900/20 border border-red-500/50 px-3 py-1.5 rounded-lg items-center gap-2 shadow-inner">
                    <i class="fas fa-stopwatch text-red-500 animate-pulse"></i>
                    <span id="sessionTimer" class="font-mono text-red-400 font-bold text-sm tracking-widest">05:00</span>
                </div>

                <button onclick="closeCheckoutModal()" class="text-slate-400 hover:text-white bg-slate-900 hover:bg-slate-700 w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center transition border border-slate-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <!-- Scrollable Form Body -->
        <form method="POST" enctype="multipart/form-data" class="p-4 sm:p-6 md:p-8 space-y-6 overflow-y-auto custom-scrollbar flex-grow">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="pass_id" id="modalPassId" value="">
            <input type="hidden" name="buy_pass" value="1">

            <!-- Total display -->
            <div class="flex justify-between items-end border-b border-slate-700 pb-4">
                <span class="text-slate-400 text-xs sm:text-sm font-medium uppercase tracking-wider">Total Due</span>
                <span class="text-2xl sm:text-3xl font-black text-yellow-400 tracking-tight drop-shadow-[0_0_10px_rgba(234,179,8,0.3)]" id="modalPassPrice">0 Ks</span>
            </div>

            <!-- Payment Nodes Grid -->
            <div class="mb-8">
                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-3">1. Select Payment Node</label>
                <div class="grid grid-cols-2 gap-3 sm:gap-4" id="paymentNodesGrid">
                    <?php foreach($payment_methods as $pm): ?>
                        <div class="payment-node cursor-pointer bg-slate-800/50 border border-slate-700 p-4 rounded-2xl relative overflow-hidden transition-all group"
                             onclick="selectPaymentNode(<?php echo htmlspecialchars(json_encode($pm)); ?>, this)">
                            <div class="flex items-center gap-3 relative z-10">
                                <div class="w-8 h-8 rounded-lg bg-slate-900 border border-slate-700 flex items-center justify-center shrink-0 group-hover:border-[#00f0ff]/50 transition-colors node-icon-box">
                                    <i class="<?php echo $pm['logo_class']; ?> text-slate-500 group-hover:text-[#00f0ff] text-sm transition-colors node-icon"></i>
                                </div>
                                <span class="font-bold text-slate-300 group-hover:text-white text-sm tracking-wide transition-colors node-text"><?php echo htmlspecialchars($pm['bank_name']); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Hidden Details Panel (Revealed on click) -->
                <div id="paymentDetailsPanel" class="hidden bg-slate-900/80 border border-yellow-500/30 rounded-2xl p-4 sm:p-5 mt-4 relative overflow-hidden animate-fade-in-down shadow-[0_0_15px_rgba(234,179,8,0.05)]">
                    <div class="absolute right-0 top-0 w-32 h-32 bg-yellow-500/10 rounded-full blur-3xl pointer-events-none"></div>
                    
                    <p class="text-[10px] text-slate-400 uppercase font-bold tracking-wider mb-1 truncate">Receiver: <span class="text-white" id="nodeAccountName">...</span></p>
                    <div class="flex items-center justify-between gap-2 bg-black/40 p-2 sm:p-3 rounded-xl border border-slate-700/50">
                        <code class="text-base sm:text-xl font-mono font-bold text-green-400 select-all truncate" id="nodeAccountNumber">...</code>
                        <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('nodeAccountNumber').innerText); this.innerHTML='<i class=\'fas fa-check text-green-400\'></i>'; setTimeout(()=>this.innerHTML='<i class=\'fas fa-copy\'></i>', 2000);" class="text-slate-500 hover:text-white bg-slate-800 p-2 rounded-lg transition shrink-0 border border-slate-700 shadow-sm">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Verification Inputs (Locked until Node is selected) -->
            <div id="verificationContainer" class="bg-slate-800/40 border border-slate-700/50 p-5 rounded-2xl space-y-5 opacity-50 pointer-events-none transition-all duration-300">
                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest border-b border-slate-700 pb-2 mb-4">2. Submit Verification</label>
                
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-2">Last 6 Digits of Transaction ID</label>
                    <div class="relative">
                        <i class="fas fa-hashtag absolute left-4 top-1/2 transform -translate-y-1/2 text-slate-500"></i>
                        <input type="text" name="txn_id" placeholder="123456" required maxlength="6" pattern="\d{6}"
                               class="w-full bg-slate-900 border border-slate-600 rounded-xl p-4 pl-10 text-white font-mono tracking-[0.5em] text-lg focus:border-yellow-500 focus:ring-1 focus:ring-yellow-500 outline-none transition shadow-inner">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-2">Payment Screenshot</label>
                    <div class="relative border-2 border-dashed border-slate-600 rounded-xl p-6 sm:p-8 text-center hover:bg-slate-800 hover:border-yellow-500/50 transition cursor-pointer group" id="uploadWrapper">
                        <input type="file" name="proof" id="proofInput" accept="image/*" required class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                        <i class="fas fa-cloud-upload-alt text-3xl sm:text-4xl text-slate-500 mb-3 group-hover:text-yellow-500 transition transform group-hover:-translate-y-1"></i>
                        <p class="text-sm font-bold text-slate-300 truncate px-2" id="fileNameDisplay">Tap to browse or drag file</p>
                        <p class="text-[10px] font-medium text-slate-500 mt-2 uppercase tracking-wider">JPG, PNG up to 5MB</p>
                    </div>
                </div>
            </div>

            <div class="pt-2 pb-4">
                <button type="submit" id="submitBtn" disabled class="w-full bg-slate-800 border border-slate-700 text-slate-500 font-black py-4 rounded-xl text-sm uppercase tracking-wider shadow-inner transition duration-300 cursor-not-allowed flex items-center justify-center gap-2">
                    <i class="fas fa-lock"></i> Awaiting Transfer
                </button>
                <p class="text-center text-[9px] sm:text-[10px] text-slate-500 mt-4 flex items-center justify-center gap-1.5 font-medium">
                    <i class="fas fa-shield-alt text-red-400"></i> Payments are manually verified. Fraud results in a ban.
                </p>
            </div>
        </form>
    </div>
</div>

<script>
    // Global Timer Vars
    let sessionTimer;
    let timeLeft = 300; // 5 minutes

    // UI Elements
    const verificationContainer = document.getElementById('verificationContainer');
    const submitBtn = document.getElementById('submitBtn');
    const timerContainer = document.getElementById('sessionTimerContainer');
    const timerDisplay = document.getElementById('sessionTimer');
    const paymentNodes = document.querySelectorAll('.payment-node');
    const detailsPanel = document.getElementById('paymentDetailsPanel');

    // Select Payment Node Logic
    function selectPaymentNode(data, element) {
        // Reset all nodes
        paymentNodes.forEach(node => {
            node.classList.remove('border-yellow-500', 'bg-yellow-500/10', 'shadow-[0_0_15px_rgba(234,179,8,0.15)]');
            node.classList.add('border-slate-700', 'bg-slate-800/50');
            node.querySelector('.node-icon').classList.remove('text-yellow-400');
            node.querySelector('.node-icon').classList.add('text-slate-500');
            node.querySelector('.node-text').classList.remove('text-yellow-400');
            node.querySelector('.node-text').classList.add('text-slate-300');
            node.querySelector('.node-icon-box').classList.remove('border-yellow-500/50');
            node.querySelector('.node-icon-box').classList.add('border-slate-700');
        });

        // Activate selected node (Using Yellow styling for Agent theme)
        element.classList.remove('border-slate-700', 'bg-slate-800/50');
        element.classList.add('border-yellow-500', 'bg-yellow-500/10', 'shadow-[0_0_15px_rgba(234,179,8,0.15)]');
        element.querySelector('.node-icon').classList.remove('text-slate-500');
        element.querySelector('.node-icon').classList.add('text-yellow-400');
        element.querySelector('.node-text').classList.remove('text-slate-300');
        element.querySelector('.node-text').classList.add('text-yellow-400');
        element.querySelector('.node-icon-box').classList.remove('border-slate-700');
        element.querySelector('.node-icon-box').classList.add('border-yellow-500/50');

        // Populate and show details
        document.getElementById('nodeAccountName').innerText = data.account_name;
        document.getElementById('nodeAccountNumber').innerText = data.account_number;
        detailsPanel.classList.remove('hidden');

        // Unlock Step 2
        verificationContainer.classList.remove('opacity-50', 'pointer-events-none');
        
        // Unlock Submit Button
        submitBtn.disabled = false;
        submitBtn.classList.remove('bg-slate-800', 'border-slate-700', 'text-slate-500', 'cursor-not-allowed', 'shadow-inner');
        submitBtn.classList.add('bg-gradient-to-r', 'from-blue-600', 'to-[#00f0ff]', 'hover:from-blue-500', 'hover:to-[#00f0ff]', 'text-slate-900', 'shadow-[0_0_20px_rgba(0,240,255,0.3)]', 'transform', 'active:scale-[0.98]');
        submitBtn.innerHTML = '<i class="fas fa-satellite-dish"></i> Execute Upgrade Protocol';

        // Start 5 min session timer
        startSessionTimer();
    }

    function startSessionTimer() {
        clearInterval(sessionTimer);
        timeLeft = 300;
        
        timerContainer.classList.remove('hidden');
        timerContainer.classList.add('flex');
        timerDisplay.classList.remove('text-red-500', 'scale-110');
        timerDisplay.classList.add('text-red-400');

        sessionTimer = setInterval(() => {
            if(timeLeft <= 0) {
                clearInterval(sessionTimer);
                alert("Secure session expired. Please refresh the matrix.");
                window.location.reload();
                return;
            }
            
            let m = Math.floor(timeLeft / 60);
            let s = timeLeft % 60;
            timerDisplay.innerText = `${m < 10 ? '0' : ''}${m}:${s < 10 ? '0' : ''}${s}`;
            
            // Visual warning at 60s
            if(timeLeft <= 60) {
                timerDisplay.classList.remove('text-red-400');
                timerDisplay.classList.add('text-red-500', 'scale-110', 'transition-transform');
            }
            timeLeft--;
        }, 1000);
    }

    // Modal Logic
    function openCheckoutModal(id, name, price) {
        document.getElementById('modalPassId').value = id;
        document.getElementById('modalPassName').innerText = name + ' Protocol';
        document.getElementById('modalPassPrice').innerText = new Intl.NumberFormat().format(price) + ' Ks';
        
        const modal = document.getElementById('checkoutModal');
        const content = document.getElementById('modalContent');
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
        
        setTimeout(() => {
            content.classList.remove('scale-95', 'opacity-0');
            content.classList.add('scale-100', 'opacity-100');
        }, 10);
    }

    function closeCheckoutModal() {
        const modal = document.getElementById('checkoutModal');
        const content = document.getElementById('modalContent');
        
        content.classList.remove('scale-100', 'opacity-100');
        content.classList.add('scale-95', 'opacity-0');
        
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = '';
            
            // Reset state if they cancel
            clearInterval(sessionTimer);
            timerContainer.classList.remove('flex');
            timerContainer.classList.add('hidden');
        }, 300);
    }

    // File Upload UI
    const proofInput = document.getElementById('proofInput');
    const fileNameDisplay = document.getElementById('fileNameDisplay');
    const uploadWrapper = document.getElementById('uploadWrapper');

    if(proofInput) {
        proofInput.addEventListener('change', function(e) {
            if(this.files && this.files[0]) {
                fileNameDisplay.innerHTML = `<span class="text-green-400 flex items-center justify-center gap-2 font-black tracking-wide"><i class="fas fa-check-circle"></i> ${this.files[0].name}</span>`;
                uploadWrapper.classList.add('border-green-500/50', 'bg-green-500/10');
            } else {
                fileNameDisplay.innerHTML = `Tap to browse or drag file`;
                uploadWrapper.classList.remove('border-green-500/50', 'bg-green-500/10');
            }
        });
    }
</script>