<?php
// modules/shop/checkout.php
// PRODUCTION DEPLOYMENT v3.0 - Interactive Payment Selection & Timer

if (!is_logged_in()) redirect('index.php?module=auth&page=login');

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 1. Fetch Product
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) die("<div class='p-10 text-center text-red-500'>Product not found</div>");

// 2. Fetch Requirements
$stmt = $pdo->prepare("SELECT * FROM product_instructions WHERE product_id = ? ORDER BY id ASC");
$stmt->execute([$product_id]);
$instructions = $stmt->fetchAll();

// 3. Fetch Active Payment Methods
$payment_methods = $pdo->query("SELECT * FROM payment_methods WHERE is_active = 1")->fetchAll();

// 4. Pricing Logic
$discount = get_user_discount($_SESSION['user_id']);
$base_price = $product['sale_price'] ?: $product['price'];
$price_after_agent = $base_price * ((100 - $discount) / 100);
$final_price = $price_after_agent;
$coupon_code = null;

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) die("Invalid Token");

    // Re-verify Coupon on Submit
    if (!empty($_POST['coupon_code'])) {
        $check_code = strtoupper(trim($_POST['coupon_code']));
        $check = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1 AND expires_at > NOW() AND used_count < max_usage");
        $check->execute([$check_code]);
        $coupon = $check->fetch();
        
        if ($coupon) {
            $coupon_code = $check_code;
            $final_price = $price_after_agent * ((100 - $coupon['discount_percent']) / 100);
            // We increment usage *after* successful order insertion
        }
    }

    // Validation
    $agreed_count = isset($_POST['agreed']) ? count($_POST['agreed']) : 0;
    $selected_payment_id = isset($_POST['payment_method_id']) ? (int)$_POST['payment_method_id'] : 0;
    
    if ($agreed_count < count($instructions)) {
        $error = "Please accept all mandatory instructions.";
    } elseif ($selected_payment_id === 0) {
        $error = "Please select a payment method.";
    } elseif (empty($_FILES['proof']['name'])) {
        $error = "Payment screenshot is required.";
    } else {
        // Upload Logic
        $target_dir = "uploads/proofs/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
        
        $ext = strtolower(pathinfo($_FILES["proof"]["name"], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($ext, $allowed)) {
            $filename = "txn_" . uniqid() . '.' . $ext;
            $target_file = $target_dir . $filename;

            if (move_uploaded_file($_FILES["proof"]["tmp_name"], $target_file)) {
                $txn_id = trim($_POST['txn_id']);
                $email_type = $_POST['email_type'];
                $delivery = ($email_type == 'own') ? $_SESSION['user_email'] : 'Admin Provided';
                
                // Construct Form Data JSON if applicable
                $form_data = null;
                if ($product['delivery_type'] === 'form' && isset($_POST['form_field'])) {
                     $form_data = json_encode($_POST['form_field']);
                }

                // Insert Order
                $sql = "INSERT INTO orders (user_id, product_id, email_delivery_type, delivery_email, form_data, payment_method_id, transaction_last_6, proof_image_path, total_price_paid, coupon_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$_SESSION['user_id'], $product_id, $email_type, $delivery, $form_data, $selected_payment_id, $txn_id, $target_file, $final_price, $coupon_code]);
                
                $new_order_id = $pdo->lastInsertId();

                // Increment Coupon Usage if applied
                if ($coupon) {
                    $pdo->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?")->execute([$coupon['id']]);
                }

                // Notifications
                if (function_exists('send_telegram_alert')) {
                    send_telegram_alert($new_order_id, $product['name'], $final_price, $_SESSION['user_name']);
                }
                
                require_once 'includes/MailService.php';
                $mailer = new MailService();
                $mailer->sendOrderConfirmation($_SESSION['user_email'], $_SESSION['user_name'], $new_order_id, $product['name'], $final_price);

                redirect('index.php?module=user&page=orders&view_chat=' . $new_order_id);
            } else {
                $error = "Failed to upload proof. Please try again.";
            }
        } else {
            $error = "Invalid file type. Only JPG/PNG allowed.";
        }
    }
}
?>

<div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-8 pb-10">
    
    <!-- LEFT: Checkout Form -->
    <div class="lg:col-span-2 space-y-6">
        
        <?php if(isset($error)) echo "<div class='bg-red-500/10 border border-red-500/30 text-red-400 p-4 rounded-xl flex items-center gap-3 animate-pulse shadow-lg'><i class='fas fa-exclamation-triangle'></i> $error</div>"; ?>

        <div class="glass p-6 md:p-8 rounded-2xl border border-slate-700 shadow-2xl relative overflow-hidden">
            <!-- Neon Accent -->
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-600 to-[#00f0ff]"></div>
            
            <h2 class="text-2xl font-bold text-white mb-6 flex items-center gap-3">
                <i class="fas fa-shopping-cart text-[#00f0ff]"></i> Checkout Configuration
            </h2>
            
            <form method="POST" enctype="multipart/form-data" id="checkoutForm">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="coupon_code" id="hidden_coupon_code">
                <input type="hidden" name="payment_method_id" id="hidden_payment_id" required>

                <!-- STEP 1: Interactive Payment Selection -->
                <div class="mb-8">
                    <div class="flex items-center justify-between mb-4 border-b border-slate-700/50 pb-2">
                        <h3 class="text-sm font-bold text-[#00f0ff] uppercase tracking-widest"><i class="fas fa-wallet mr-2"></i> 1. Select Payment Method</h3>
                    </div>
                    
                    <!-- Selection Grid -->
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4" id="paymentGrid">
                        <?php foreach($payment_methods as $pm): ?>
                            <div class="payment-card cursor-pointer bg-slate-800/50 border border-slate-700 hover:border-[#00f0ff]/50 rounded-xl p-4 text-center transition-all duration-300 group" 
                                 onclick="selectPayment(<?php echo htmlspecialchars(json_encode($pm)); ?>, this)">
                                <i class="<?php echo htmlspecialchars($pm['logo_class']); ?> text-2xl text-slate-400 group-hover:text-[#00f0ff] mb-2 transition-colors"></i>
                                <p class="text-xs font-bold text-white"><?php echo htmlspecialchars($pm['bank_name']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Hidden Details Panel (Revealed on click) -->
                    <div id="paymentDetailsPanel" class="hidden bg-blue-900/10 border border-blue-500/30 rounded-xl p-5 relative overflow-hidden animate-fade-in-down">
                        <div class="absolute right-0 top-0 w-32 h-32 bg-blue-500/10 rounded-full blur-3xl pointer-events-none"></div>
                        
                        <div class="flex justify-between items-start mb-3 relative z-10">
                            <div>
                                <p class="text-[10px] text-blue-400 uppercase font-bold tracking-widest mb-1">Transfer Exactly</p>
                                <p class="text-2xl font-black text-white tracking-tight" id="transferAmountDisplay"><?php echo format_price($final_price); ?></p>
                            </div>
                            <!-- 5 Minute Session Timer -->
                            <div class="bg-red-900/30 border border-red-500/30 px-3 py-1.5 rounded-lg flex items-center gap-2">
                                <i class="fas fa-stopwatch text-red-400 animate-pulse"></i>
                                <span id="sessionTimer" class="font-mono text-red-400 font-bold text-sm">05:00</span>
                            </div>
                        </div>

                        <div class="bg-slate-900/80 p-4 rounded-lg border border-slate-700 relative z-10">
                            <p class="text-xs text-slate-400 mb-1">Receiver Name: <strong class="text-white ml-1" id="receiverName">...</strong></p>
                            <p class="text-xs text-slate-400 mb-1">Account Number:</p>
                            <div class="flex items-center gap-3">
                                <code class="text-lg font-mono text-green-400 font-bold select-all break-all" id="accountNumber">...</code>
                                <button type="button" onclick="copyAccountInfo()" class="text-slate-500 hover:text-white transition p-1" title="Copy Number">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 2: Proof & Transaction ID (Locked until payment selected) -->
                <div id="step2Container" class="opacity-50 pointer-events-none transition-all duration-300 mb-8">
                    <div class="flex items-center justify-between mb-4 border-b border-slate-700/50 pb-2">
                        <h3 class="text-sm font-bold text-[#00f0ff] uppercase tracking-widest"><i class="fas fa-file-invoice mr-2"></i> 2. Submit Verification</h3>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 mb-2">Last 6 Digits of Txn ID</label>
                            <input type="text" name="txn_id" placeholder="e.g. 123456" required maxlength="6"
                                   class="w-full bg-slate-900 border border-slate-600 rounded-xl p-3.5 text-white font-mono focus:border-[#00f0ff] focus:ring-1 focus:ring-[#00f0ff] outline-none transition shadow-inner">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-400 mb-2">Upload Screenshot</label>
                            <div class="relative border-2 border-dashed border-slate-600 rounded-xl p-3 text-center hover:bg-slate-800 hover:border-[#00f0ff]/50 transition cursor-pointer group" id="uploadWrapper">
                                <input type="file" name="proof" accept="image/*" required class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" 
                                       onchange="document.getElementById('fileNameDisplay').innerHTML = `<span class='text-green-400 font-bold'><i class='fas fa-check-circle'></i> ` + this.files[0].name + `</span>`; this.parentElement.classList.add('border-green-500/50', 'bg-green-500/5');">
                                <i class="fas fa-cloud-upload-alt text-xl text-slate-500 mb-1 group-hover:text-[#00f0ff] transition"></i>
                                <p class="text-xs font-medium text-slate-400" id="fileNameDisplay">Upload receipt</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 3: Form Fields (If product type is Form) -->
                <?php if($product['delivery_type'] == 'form' && $product['form_fields']): ?>
                <div class="mb-8 bg-blue-900/5 border border-blue-500/20 p-5 rounded-xl">
                    <h3 class="text-sm font-bold text-blue-400 uppercase tracking-widest mb-4"><i class="fas fa-user-edit mr-2"></i> Target Account Details</h3>
                    <div class="space-y-4">
                    <?php 
                        $fields = json_decode($product['form_fields'], true);
                        if(is_array($fields)){
                            foreach($fields as $idx => $f): 
                    ?>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 mb-1"><?php echo htmlspecialchars($f['label']); ?></label>
                            <input type="<?php echo htmlspecialchars($f['type'] ?? 'text'); ?>" name="form_field[<?php echo htmlspecialchars($f['label']); ?>]" required 
                                   class="w-full bg-slate-900 border border-slate-600 rounded-lg p-3 text-white focus:border-blue-500 outline-none">
                        </div>
                    <?php 
                            endforeach; 
                        } 
                    ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- STEP 4: Delivery Options -->
                <?php if($product['delivery_type'] != 'form'): ?>
                <div class="mb-8">
                    <h3 class="text-sm font-bold text-slate-300 mb-3">Delivery Email Setup</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <label class="border border-slate-600 bg-slate-800/50 p-4 rounded-xl cursor-pointer has-[:checked]:border-[#00f0ff] has-[:checked]:bg-[#00f0ff]/5 transition group">
                            <div class="flex items-center gap-3">
                                <input type="radio" name="email_type" value="own" checked class="w-4 h-4 text-[#00f0ff] bg-slate-900 border-slate-600 focus:ring-[#00f0ff] focus:ring-offset-slate-900">
                                <div>
                                    <span class="block text-sm font-bold text-white group-hover:text-[#00f0ff] transition">My Account Email</span>
                                    <span class="text-xs text-slate-500 font-mono"><?php echo htmlspecialchars($_SESSION['user_email']); ?></span>
                                </div>
                            </div>
                        </label>
                        <label class="border border-slate-600 bg-slate-800/50 p-4 rounded-xl cursor-pointer has-[:checked]:border-[#00f0ff] has-[:checked]:bg-[#00f0ff]/5 transition group relative overflow-hidden">
                            <div class="flex items-center gap-3 relative z-10">
                                <input type="radio" name="email_type" value="admin" class="w-4 h-4 text-[#00f0ff] bg-slate-900 border-slate-600 focus:ring-[#00f0ff] focus:ring-offset-slate-900">
                                <div>
                                    <span class="block text-sm font-bold text-white group-hover:text-[#00f0ff] transition">Admin Setup</span>
                                    <span class="text-xs text-slate-500">Provide via Chat</span>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
                <?php else: ?>
                    <input type="hidden" name="email_type" value="own">
                <?php endif; ?>

                <!-- STEP 5: Mandatory Instructions -->
                <?php if(!empty($instructions)): ?>
                <div class="mb-8 bg-red-900/10 border border-red-500/20 p-5 rounded-xl">
                    <h3 class="font-bold text-red-400 mb-3 flex items-center gap-2"><i class="fas fa-exclamation-triangle"></i> Mandatory Agreement</h3>
                    <div class="space-y-3">
                        <?php foreach($instructions as $ins): ?>
                            <label class="flex items-start gap-3 cursor-pointer group">
                                <input type="checkbox" name="agreed[]" value="<?php echo $ins['id']; ?>" required 
                                       class="mt-1 w-4 h-4 rounded border-slate-600 bg-slate-800 text-red-500 focus:ring-red-500 focus:ring-offset-slate-900 transition">
                                <span class="text-sm text-slate-300 group-hover:text-white transition leading-snug"><?php echo htmlspecialchars($ins['instruction_text']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <button type="submit" id="finalSubmitBtn" disabled class="w-full bg-slate-700 text-slate-400 font-black py-4 rounded-xl text-sm uppercase tracking-widest transition-all duration-300 cursor-not-allowed">
                    Select Payment Method First
                </button>

            </form>
        </div>
    </div>

    <!-- RIGHT: Summary & Promo -->
    <div class="lg:col-span-1">
        <div class="glass p-6 rounded-2xl border border-slate-700 sticky top-24 shadow-2xl">
            
            <div class="flex items-center gap-4 mb-6 pb-6 border-b border-slate-700">
                <div class="w-14 h-14 bg-slate-800 rounded-xl flex items-center justify-center text-blue-500 text-2xl border border-slate-600 shadow-inner">
                    <i class="fas <?php echo htmlspecialchars($product['icon_class'] ?? 'fa-cube'); ?>"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-white leading-tight line-clamp-2"><?php echo htmlspecialchars($product['name']); ?></h3>
                    <span class="text-[10px] uppercase font-bold text-slate-500 bg-slate-900 px-2 py-0.5 rounded border border-slate-700 mt-1 inline-block"><?php echo htmlspecialchars($product['delivery_type']); ?> Delivery</span>
                </div>
            </div>

            <!-- Promo UI -->
            <div class="bg-slate-900/50 p-4 rounded-xl border border-slate-700 mb-6">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Apply Promo Code</label>
                <div class="flex gap-2">
                    <input type="text" id="coupon_input" placeholder="e.g. SAVE20" class="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm focus:border-yellow-500 outline-none uppercase font-bold">
                    <button type="button" onclick="applyCoupon()" class="bg-yellow-600 hover:bg-yellow-500 text-slate-900 px-4 py-2 rounded-lg text-xs font-bold transition shadow-md">APPLY</button>
                </div>
                <p id="coupon_msg" class="text-xs mt-2 hidden flex items-center gap-1"></p>
            </div>

            <!-- Pricing Breakdown -->
            <div class="space-y-3 mb-6">
                <div class="flex justify-between text-sm">
                    <span class="text-slate-400">Regular Price</span>
                    <span class="text-white <?php echo ($discount > 0 || $product['sale_price']) ? 'line-through decoration-slate-500 opacity-50' : ''; ?>">
                        <?php echo format_price($product['price']); ?>
                    </span>
                </div>
                
                <?php if($product['sale_price']): ?>
                <div class="flex justify-between text-sm">
                    <span class="text-slate-400">Sale Price</span>
                    <span class="text-white"><?php echo format_price($product['sale_price']); ?></span>
                </div>
                <?php endif; ?>

                <?php if($discount > 0): ?>
                <div class="flex justify-between text-sm">
                    <span class="text-yellow-400 flex items-center gap-1"><i class="fas fa-crown text-[10px]"></i> Agent Offset (-<?php echo $discount; ?>%)</span>
                    <span class="text-yellow-400 font-bold">- <?php echo format_price($base_price - $price_after_agent); ?></span>
                </div>
                <?php endif; ?>

                <div id="discount_row" class="flex justify-between text-sm hidden">
                    <span class="text-green-400 flex items-center gap-1"><i class="fas fa-ticket-alt text-[10px]"></i> Promo Code</span>
                    <span class="text-green-400 font-bold" id="discount_val">0%</span>
                </div>
            </div>

            <div class="flex justify-between items-end border-t border-slate-700 pt-4">
                <span class="text-slate-300 font-bold uppercase text-xs tracking-wider">Total</span>
                <span class="text-3xl font-black text-[#00f0ff] tracking-tight drop-shadow-[0_0_8px_rgba(0,240,255,0.5)]" id="final_price_display">
                    <?php echo format_price($price_after_agent); ?>
                </span>
            </div>
            
            <div class="mt-4 text-center text-[10px] text-slate-500 flex items-center justify-center gap-2">
                <i class="fas fa-shield-alt"></i> SSL Encrypted Checkout
            </div>
        </div>
    </div>

</div>

<!-- JS Logic for Session & Interactions -->
<script>
    let currentBasePrice = <?php echo $price_after_agent; ?>; 
    let currentFinalPrice = <?php echo $price_after_agent; ?>;
    
    // Timer Variables
    let sessionTimer;
    let timeLeft = 300; // 5 minutes in seconds

    // Elements
    const pGrid = document.querySelectorAll('.payment-card');
    const panel = document.getElementById('paymentDetailsPanel');
    const inputHiddenId = document.getElementById('hidden_payment_id');
    const step2 = document.getElementById('step2Container');
    const submitBtn = document.getElementById('finalSubmitBtn');
    const timerDisplay = document.getElementById('sessionTimer');

    function selectPayment(data, element) {
        // Highlight selection
        pGrid.forEach(el => {
            el.classList.remove('border-[#00f0ff]', 'bg-[#00f0ff]/10', 'shadow-[0_0_15px_rgba(0,240,255,0.2)]');
            el.classList.add('border-slate-700', 'bg-slate-800/50');
            el.querySelector('i').classList.remove('text-[#00f0ff]');
            el.querySelector('i').classList.add('text-slate-400');
        });
        
        element.classList.remove('border-slate-700', 'bg-slate-800/50');
        element.classList.add('border-[#00f0ff]', 'bg-[#00f0ff]/10', 'shadow-[0_0_15px_rgba(0,240,255,0.2)]');
        element.querySelector('i').classList.remove('text-slate-400');
        element.querySelector('i').classList.add('text-[#00f0ff]');

        // Update Panel Data
        document.getElementById('receiverName').innerText = data.account_name;
        document.getElementById('accountNumber').innerText = data.account_number;
        inputHiddenId.value = data.id;

        // Reveal Panel & Step 2
        panel.classList.remove('hidden');
        step2.classList.remove('opacity-50', 'pointer-events-none');
        
        // Enable Submit Button
        submitBtn.disabled = false;
        submitBtn.classList.remove('bg-slate-700', 'text-slate-400', 'cursor-not-allowed');
        submitBtn.classList.add('bg-gradient-to-r', 'from-blue-600', 'to-[#00f0ff]', 'hover:from-blue-500', 'hover:to-[#00f0ff]', 'text-slate-900', 'shadow-[0_0_20px_rgba(0,240,255,0.3)]', 'hover:scale-[1.02]');
        submitBtn.innerHTML = '<i class="fas fa-lock mr-2"></i> Confirm Payment Transfer';

        // Start Timer
        startSessionTimer();
    }

    function startSessionTimer() {
        clearInterval(sessionTimer);
        timeLeft = 300; // Reset to 5 mins
        
        sessionTimer = setInterval(() => {
            if(timeLeft <= 0) {
                clearInterval(sessionTimer);
                alert("Payment session expired for security. Please refresh the page.");
                window.location.reload();
                return;
            }
            
            let m = Math.floor(timeLeft / 60);
            let s = timeLeft % 60;
            timerDisplay.innerText = `${m < 10 ? '0' : ''}${m}:${s < 10 ? '0' : ''}${s}`;
            
            // Visual warning at 1 minute
            if(timeLeft <= 60) {
                timerDisplay.classList.add('text-red-500', 'scale-110', 'transition-transform');
            }
            timeLeft--;
        }, 1000);
    }

    function copyAccountInfo() {
        const text = document.getElementById('accountNumber').innerText;
        navigator.clipboard.writeText(text).then(() => {
            alert("Account number copied to clipboard!");
        });
    }

    function applyCoupon() {
        const code = document.getElementById('coupon_input').value;
        const msg = document.getElementById('coupon_msg');
        
        fetch('api/coupon.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({code: code})
        })
        .then(res => res.json())
        .then(data => {
            msg.classList.remove('hidden', 'text-red-400', 'text-green-400');
            if(data.valid) {
                msg.classList.add('text-green-400');
                msg.innerHTML = `<i class="fas fa-check-circle"></i> ${data.message}`;
                
                const discountPct = data.discount_percent;
                currentFinalPrice = currentBasePrice * ((100 - discountPct) / 100);
                
                // Format price string manually assuming Ks
                let priceStr = new Intl.NumberFormat().format(currentFinalPrice) + ' Ks';
                
                document.getElementById('final_price_display').innerText = priceStr;
                document.getElementById('transferAmountDisplay').innerText = priceStr;
                
                document.getElementById('discount_row').classList.remove('hidden');
                document.getElementById('discount_val').innerText = `-${discountPct}%`;
                
                document.getElementById('hidden_coupon_code').value = code;
                document.getElementById('coupon_input').readOnly = true;
                document.getElementById('coupon_input').classList.add('opacity-50');
            } else {
                msg.classList.add('text-red-400');
                msg.innerHTML = `<i class="fas fa-times-circle"></i> ${data.message}`;
            }
        });
    }
</script>