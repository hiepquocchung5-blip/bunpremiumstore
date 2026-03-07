<?php
// modules/user/agent.php
// PRODUCTION DEPLOYMENT v2.1 - Enhanced Mobile UI & Modal Scrolling

// Auth Guard
if (!is_logged_in()) redirect('index.php?module=auth&page=login');

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// =====================================================================================
// 1. HANDLE PASS PURCHASE (Real Transaction Flow)
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
                        'Pass ID' => $pass['id'],
                        'Pass Name' => $pass['name'],
                        'Duration' => $pass['duration_days'] . ' Days'
                    ]);

                    // Let's check if dummy product 0 exists, if not create it.
                    $pdo->exec("INSERT IGNORE INTO products (id, category_id, name, price, delivery_type) VALUES (0, 1, 'System: Agent Pass Upgrade', 0, 'universal')");

                    $sql = "INSERT INTO orders (user_id, product_id, email_delivery_type, delivery_email, form_data, transaction_last_6, proof_image_path, total_price_paid, status) 
                            VALUES (?, 0, 'own', ?, ?, ?, ?, ?, 'pending')";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$user_id, $_SESSION['user_email'], $form_data, $txn_id, $target_file, $pass['price']]);
                    
                    $order_id = $pdo->lastInsertId();
                    
                    // Notify Admin
                    if (function_exists('send_telegram_alert')) {
                        send_telegram_alert($order_id, "Agent Pass Upgrade: " . $pass['name'], $pass['price'], $_SESSION['user_name']);
                    }
                    
                    $success = "Upgrade requested! Admin will verify your payment and activate your pass shortly.";
                    
                } catch (PDOException $e) {
                    $error = "System error processing request. " . $e->getMessage();
                }
            } else {
                $error = "Failed to upload proof. Check folder permissions.";
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
?>

<div class="max-w-6xl mx-auto animate-fade-in-down px-2 sm:px-0">
    
    <!-- Header Section -->
    <div class="text-center mb-10 md:mb-14 relative">
        <div class="absolute inset-0 flex items-center justify-center opacity-10 pointer-events-none">
            <i class="fas fa-crown text-8xl md:text-9xl text-yellow-500 blur-2xl"></i>
        </div>
        <h1 class="text-3xl sm:text-4xl md:text-5xl font-black mb-3 text-transparent bg-clip-text bg-gradient-to-r from-yellow-200 via-yellow-400 to-yellow-600 drop-shadow-sm tracking-tight relative z-10">
            Agent Partnership
        </h1>
        <p class="text-slate-400 max-w-2xl mx-auto text-sm sm:text-base leading-relaxed relative z-10 px-4">
            Unlock wholesale pricing on all digital products. Start your own reselling business today with our premium membership tiers.
        </p>
    </div>

    <!-- Notifications -->
    <?php if($success): ?>
        <div class="max-w-2xl mx-auto mb-8 bg-green-500/10 border border-green-500/30 text-green-400 p-5 md:p-6 rounded-2xl flex items-center gap-4 shadow-[0_0_20px_rgba(34,197,94,0.15)] relative z-10">
            <div class="w-10 h-10 md:w-12 md:h-12 bg-green-500/20 rounded-full flex items-center justify-center text-green-400 border border-green-500/50 shrink-0">
                <i class="fas fa-check text-lg md:text-xl"></i>
            </div>
            <div>
                <h4 class="font-bold text-base md:text-lg">Request Submitted!</h4>
                <p class="text-xs md:text-sm opacity-90 mt-1">Please check your <a href="index.php?module=user&page=orders" class="underline font-bold text-white hover:text-green-300">Orders Page</a> to track the status of your upgrade.</p>
            </div>
        </div>
    <?php endif; ?>

    <?php if($error): ?>
        <div class="max-w-2xl mx-auto mb-8 bg-red-500/10 border border-red-500/30 text-red-400 p-4 rounded-xl flex items-center gap-3 animate-pulse relative z-10">
            <i class="fas fa-exclamation-triangle text-lg shrink-0"></i>
            <span class="text-sm font-medium"><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <!-- Plans Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 xl:gap-8 relative z-10 mb-10">
        
        <!-- Standard / Free Tier Info -->
        <div class="bg-slate-900/80 backdrop-blur-xl p-6 sm:p-8 rounded-3xl border border-slate-700/50 flex flex-col grayscale opacity-80 hover:opacity-100 hover:grayscale-0 transition duration-500 shadow-xl">
            <div class="mb-6">
                <span class="text-[10px] font-bold tracking-widest text-slate-500 uppercase">Current Status</span>
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
                Default Plan
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
                    
                    <!-- Decorative Background -->
                    <div class="absolute -right-10 -top-10 w-32 h-32 bg-yellow-500/10 rounded-full blur-3xl pointer-events-none"></div>

                    <?php if($isUpgrade && !$isActive): ?>
                        <div class="absolute top-0 right-0 bg-gradient-to-r from-yellow-500 to-yellow-600 text-slate-900 text-[10px] font-black px-4 py-1.5 rounded-bl-2xl uppercase tracking-wider shadow-lg">
                            Recommended
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
                        <div class="flex items-center gap-4 bg-slate-800/80 p-3 sm:p-4 rounded-2xl border border-yellow-500/20 shadow-inner">
                            <div class="w-12 h-12 rounded-xl bg-yellow-500/10 flex items-center justify-center text-yellow-400 font-black text-xl border border-yellow-500/30 shadow-[0_0_15px_rgba(234,179,8,0.2)] shrink-0">
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
                                Acquire Pass <i class="fas fa-arrow-right"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ===================================================================================== -->
<!-- PASS CHECKOUT MODAL (Responsive Mobile Scrolling)                                     -->
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
            <button onclick="closeCheckoutModal()" class="text-slate-400 hover:text-white bg-slate-800 hover:bg-slate-700 w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center transition relative z-10 border border-slate-600">
                <i class="fas fa-times"></i>
            </button>
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

            <!-- Payment Methods Grid -->
            <div>
                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-3">1. Transfer Funds To</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                    <?php foreach($payment_methods as $pm): ?>
                        <div class="bg-slate-800/50 border border-slate-700 p-4 rounded-2xl relative overflow-hidden group hover:border-[#00f0ff]/30 transition-colors">
                            <div class="absolute top-0 right-0 w-16 h-16 bg-blue-500/5 rounded-full blur-xl pointer-events-none"></div>
                            <div class="flex items-center gap-3 mb-2 relative z-10">
                                <div class="w-8 h-8 rounded-lg bg-slate-900 border border-slate-700 flex items-center justify-center shrink-0">
                                    <i class="<?php echo $pm['logo_class']; ?> text-[#00f0ff] text-sm"></i>
                                </div>
                                <span class="font-bold text-white text-sm tracking-wide"><?php echo htmlspecialchars($pm['bank_name']); ?></span>
                            </div>
                            <div class="relative z-10 bg-slate-900/50 p-2.5 rounded-xl border border-slate-700/50 mt-3">
                                <p class="text-[10px] text-slate-400 uppercase font-bold tracking-wider mb-1 truncate"><?php echo htmlspecialchars($pm['account_name']); ?></p>
                                <div class="flex items-center justify-between gap-2">
                                    <code class="text-sm sm:text-base font-mono font-bold text-green-400 select-all truncate"><?php echo htmlspecialchars($pm['account_number']); ?></code>
                                    <button type="button" onclick="navigator.clipboard.writeText('<?php echo addslashes($pm['account_number']); ?>'); this.innerHTML='<i class=\'fas fa-check\'></i>'; setTimeout(()=>this.innerHTML='<i class=\'fas fa-copy\'></i>', 2000);" class="text-slate-500 hover:text-white bg-slate-800 p-1.5 rounded-lg transition shrink-0">
                                        <i class="fas fa-copy text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Verification Inputs -->
            <div class="bg-slate-800/40 border border-slate-700/50 p-5 rounded-2xl space-y-5">
                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest border-b border-slate-700 pb-2 mb-4">2. Submit Verification</label>
                
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-2">Last 6 Digits of Transaction ID</label>
                    <input type="text" name="txn_id" placeholder="e.g. 123456" required maxlength="6" pattern="\d{6}"
                           class="w-full bg-slate-900 border border-slate-600 rounded-xl p-4 text-white font-mono tracking-widest text-center text-lg focus:border-yellow-500 focus:ring-1 focus:ring-yellow-500 outline-none transition shadow-inner">
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
                <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-[#00f0ff] hover:from-blue-500 hover:to-[#00f0ff] text-slate-900 font-black py-4 rounded-xl text-sm uppercase tracking-wider shadow-[0_0_20px_rgba(0,240,255,0.3)] transform transition active:scale-[0.98] flex items-center justify-center gap-2">
                    <i class="fas fa-lock"></i> Initialize Secure Checkout
                </button>
                <p class="text-center text-[9px] sm:text-[10px] text-slate-500 mt-4 flex items-center justify-center gap-1.5 font-medium">
                    <i class="fas fa-shield-alt text-red-400"></i> Payments are manually verified. Fraud results in a ban.
                </p>
            </div>
        </form>
    </div>
</div>

<script>
    // Modal Logic with Body Scroll Lock
    function openCheckoutModal(id, name, price) {
        document.getElementById('modalPassId').value = id;
        document.getElementById('modalPassName').innerText = name + ' Protocol';
        document.getElementById('modalPassPrice').innerText = new Intl.NumberFormat().format(price) + ' Ks';
        
        const modal = document.getElementById('checkoutModal');
        const content = document.getElementById('modalContent');
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden'; // Lock background scrolling
        
        // Small delay for animation
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
            document.body.style.overflow = ''; // Restore background scrolling
        }, 300);
    }

    // File Upload UI Update
    const proofInput = document.getElementById('proofInput');
    const fileNameDisplay = document.getElementById('fileNameDisplay');
    const uploadWrapper = document.getElementById('uploadWrapper');

    if(proofInput) {
        proofInput.addEventListener('change', function(e) {
            if(this.files && this.files[0]) {
                fileNameDisplay.innerHTML = `<span class="text-green-400 flex items-center justify-center gap-2 font-black tracking-wide"><i class="fas fa-check-circle"></i> ${this.files[0].name}</span>`;
                uploadWrapper.classList.add('border-green-500/50', 'bg-green-500/10');
            } else {
                // Reset if user cancels file selection
                fileNameDisplay.innerHTML = `Tap to browse or drag file`;
                uploadWrapper.classList.remove('border-green-500/50', 'bg-green-500/10');
            }
        });
    }
</script>