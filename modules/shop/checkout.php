<?php
// modules/shop/checkout.php
// PRODUCTION DEPLOYMENT v4.4 - 10-Min Cooldown Engine & Double-Submit Protection

if (!is_logged_in()) redirect('index.php?module=auth&page=login');

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'];

// 1. Fetch Product & Category Info
$stmt = $pdo->prepare("
    SELECT p.*, c.name as cat_name, c.image_url as cat_image 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.id = ?
");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) die("<div class='p-10 text-center text-red-500'>Product not found</div>");

// 2. Fetch Requirements
$stmt = $pdo->prepare("SELECT * FROM product_instructions WHERE product_id = ? ORDER BY id ASC");
$stmt->execute([$product_id]);
$instructions = $stmt->fetchAll();

// 3. Fetch Active Payment Methods (Standard Gateways Only)
$payment_methods = $pdo->query("SELECT * FROM payment_methods WHERE is_active = 1")->fetchAll();

// 4. Pricing Logic
$discount = get_user_discount($user_id);
$base_price = $product['sale_price'] ?: $product['price'];
$price_after_agent = $base_price * ((100 - $discount) / 100);
$final_price = $price_after_agent;
$coupon_code = null;

// =====================================================================================
// 5. ANTI-SPAM / RATE LIMITING ENGINE (10 Minute Cooldown)
// =====================================================================================
$stmt_last = $pdo->prepare("SELECT created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt_last->execute([$user_id]);
$last_order = $stmt_last->fetchColumn();

$cooldown_seconds = 600; // 10 minutes
$on_cooldown = false;
$time_remaining = 0;

if ($last_order) {
    $elapsed = time() - strtotime($last_order);
    if ($elapsed < $cooldown_seconds) {
        $on_cooldown = true;
        $time_remaining = $cooldown_seconds - $elapsed;
    }
}

// =====================================================================================
// HANDLE FORM SUBMISSION (Strictly Manual Transfers)
// =====================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) die("Invalid Token");

    if ($on_cooldown) {
        $error = "Network rate limit active. Please wait for the cooldown to expire.";
    } else {
        // Re-verify Coupon on Submit
        if (!empty($_POST['coupon_code'])) {
            $check_code = strtoupper(trim($_POST['coupon_code']));
            $check = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1 AND expires_at > NOW() AND used_count < max_usage");
            $check->execute([$check_code]);
            $coupon = $check->fetch();
            
            if ($coupon) {
                $coupon_code = $check_code;
                $final_price = $price_after_agent * ((100 - $coupon['discount_percent']) / 100);
            }
        }

        // Validation
        $agreed_count = isset($_POST['agreed']) ? count($_POST['agreed']) : 0;
        $selected_payment_id = isset($_POST['payment_method_id']) ? (int)$_POST['payment_method_id'] : 0;
        
        if ($agreed_count < count($instructions)) {
            $error = "Please accept all mandatory security protocols.";
        } elseif ($selected_payment_id === 0) {
            $error = "Please select a payment node.";
        } elseif (empty($_FILES['proof']['name'])) {
            $error = "Payment verification screenshot is required.";
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
                    
                    // Fetch Payment Method Name for logs
                    $stmt_pm = $pdo->prepare("SELECT bank_name FROM payment_methods WHERE id = ?");
                    $stmt_pm->execute([$selected_payment_id]);
                    $pm_name = $stmt_pm->fetchColumn() ?: 'Manual Transfer';

                    // Form Data JSON setup
                    $form_data_array = ['Payment Node' => $pm_name];
                    if ($product['delivery_type'] === 'form' && isset($_POST['form_field'])) {
                         $form_data_array = array_merge($form_data_array, $_POST['form_field']);
                    }
                    $form_data = json_encode($form_data_array);

                    // STRICT ADMIN PROVISIONING
                    $email_type = 'admin_provided';
                    $delivery = 'Secure Admin Delivery via Chat';
                    $order_status = 'pending';

                    // Insert Order
                    $sql = "INSERT INTO orders (user_id, product_id, email_delivery_type, delivery_email, form_data, transaction_last_6, proof_image_path, total_price_paid, coupon_code, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$user_id, $product_id, $email_type, $delivery, $form_data, $txn_id, $target_file, $final_price, $coupon_code, $order_status]);
                    $new_order_id = $pdo->lastInsertId();

                    // Increment Coupon Usage
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

                    // --- PUSH NOTIFICATION TO USER FOR PENDING ORDER ---
                    if (file_exists('includes/PushService.php')) {
                        require_once 'includes/PushService.php';
                        try {
                            $push = new PushService($pdo);
                            $target_url = BASE_URL . "index.php?module=user&page=orders&view_chat=" . $new_order_id;
                            $push->sendToUser($user_id, "Order Processing ⏳", "Your payment for Order #{$new_order_id} is currently under verification.", $target_url);
                        } catch (Exception $e) {}
                    }

                    redirect('index.php?module=user&page=orders&view_chat=' . $new_order_id);
                } else {
                    $error = "Failed to upload proof. Please try again.";
                }
            } else {
                $error = "Invalid file type. Only JPG/PNG allowed.";
            }
        }
    }
}

// Calculate savings for UI
$sale_savings = $product['price'] - $base_price;
$agent_savings = $base_price - $price_after_agent;

// Image Selection for Poster
$display_image = !empty($product['image_path']) ? BASE_URL . $product['image_path'] : (!empty($product['cat_image']) ? BASE_URL . $product['cat_image'] : '');
?>

<style>
    /* Animated Gradient Background */
    .bg-animated-gradient {
        background: linear-gradient(-45deg, #0f172a, #1e1b4b, #0f172a, #064e3b);
        background-size: 400% 400%;
        animation: gradientBG 15s ease infinite;
    }
    @keyframes gradientBG {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }
    
    /* Cinematic Image Pan */
    @keyframes panImage {
        0% { transform: scale(1.05) translate(0, 0); }
        50% { transform: scale(1.15) translate(-2%, -2%); }
        100% { transform: scale(1.05) translate(0, 0); }
    }
    .animate-pan-image {
        animation: panImage 20s ease-in-out infinite;
    }

    /* Input Label Floating */
    .input-wrapper { position: relative; }
    .input-wrapper input:focus + label,
    .input-wrapper input:not(:placeholder-shown) + label {
        transform: translateY(-130%) scale(0.85);
        color: #00f0ff;
        background-color: #0f172a;
        padding: 0 4px;
    }
</style>

<div class="bg-animated-gradient fixed inset-0 w-full h-full -z-20"></div>

<div class="max-w-7xl mx-auto px-4 py-8 lg:py-12 animate-fade-in-down relative z-10">
    
    <div class="flex items-center gap-3 mb-8">
        <a href="javascript:history.back()" class="w-10 h-10 rounded-xl bg-slate-800/80 hover:bg-slate-700 border border-slate-700 text-slate-400 hover:text-white flex items-center justify-center transition shadow-lg backdrop-blur">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="text-3xl font-black text-white tracking-tight">Acquisition Node</h1>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10 items-start">
        
        <!-- LEFT: Checkout Form / Cooldown UI (8 columns) -->
        <div class="lg:col-span-8 space-y-6">
            
            <?php if(isset($error)) echo "<div class='bg-red-900/20 border border-red-500/50 text-red-400 p-4 rounded-2xl flex items-start gap-3 animate-pulse shadow-lg backdrop-blur-md'><i class='fas fa-exclamation-triangle mt-1'></i> $error</div>"; ?>

            <?php if($on_cooldown): ?>
                <!-- COOLDOWN TERMINAL UI -->
                <div class="bg-slate-900/80 backdrop-blur-xl border border-red-500/40 p-8 md:p-12 rounded-3xl shadow-[0_20px_50px_rgba(239,68,68,0.15)] text-center relative overflow-hidden">
                    <!-- Matrix Dots Overlay -->
                    <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PHBhdHRlcm4gaWQ9ImdyaWQiIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCIgcGF0dGVyblVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+PHBhdGggZD0iTSAwIDEwIEwgNDAgMTAgTSAxMCAwIEwgMTAgNDAiIGZpbGw9Im5vbmUiIHN0cm9rZT0icmdiYSgyMzksIDY4LCA2OCwgMC4wNSkiIHN0cm9rZS13aWR0aD0iMSIvPjwvcGF0dGVybj48L2RlZnM+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0idXJsKCNncmlkKSIvPjwvc3ZnPg==')] opacity-50"></div>
                    
                    <div class="absolute -right-20 -top-20 w-64 h-64 bg-red-500/10 rounded-full blur-3xl pointer-events-none"></div>

                    <div class="w-24 h-24 bg-red-500/10 rounded-full flex items-center justify-center mx-auto mb-6 border border-red-500/30 shadow-[0_0_30px_rgba(239,68,68,0.3)] relative z-10">
                        <i class="fas fa-shield-alt text-4xl text-red-500 animate-pulse"></i>
                    </div>
                    
                    <h2 class="text-3xl font-black text-white tracking-tight mb-3 relative z-10">Network Cooldown Active</h2>
                    <p class="text-slate-400 text-sm mb-10 leading-relaxed max-w-md mx-auto relative z-10">
                        To ensure secure provisioning and prevent matrix congestion, operatives must wait 10 minutes between consecutive acquisitions.
                    </p>
                    
                    <div class="inline-flex flex-col items-center justify-center bg-slate-950 border border-red-500/50 rounded-2xl p-6 min-w-[220px] shadow-inner relative z-10">
                        <span class="text-[10px] font-bold text-red-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                            <i class="fas fa-clock animate-spin-slow"></i> Time Until Unlock
                        </span>
                        <span id="cooldownDisplay" class="text-5xl font-mono font-black text-white drop-shadow-[0_0_10px_rgba(239,68,68,0.5)]">
                            00:00
                        </span>
                    </div>

                    <div class="mt-10 relative z-10">
                        <a href="index.php?module=user&page=orders" class="inline-flex items-center gap-2 text-sm text-slate-400 hover:text-white transition uppercase tracking-wider font-bold">
                            <i class="fas fa-history"></i> Check Pending Orders
                        </a>
                    </div>
                </div>
            <?php else: ?>

                <!-- NORMAL CHECKOUT FORM -->
                <form method="POST" enctype="multipart/form-data" id="checkoutForm" class="space-y-6" onsubmit="handleSecureSubmit(event)">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="coupon_code" id="hidden_coupon_code">
                    <input type="hidden" name="payment_method_id" id="hidden_payment_id" required>
                    
                    <!-- Hidden strict delivery enforcement -->
                    <input type="hidden" name="email_type" value="admin_provided">

                    <!-- STEP 1: Interactive Payment Selection -->
                    <div class="bg-slate-900/80 backdrop-blur-xl p-6 md:p-8 rounded-3xl border border-slate-700/50 shadow-[0_10px_30px_rgba(0,0,0,0.3)] relative overflow-hidden group">
                        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-600 via-[#00f0ff] to-blue-600 bg-[length:200%_auto] animate-gradient"></div>
                        
                        <div class="flex items-center justify-between mb-6 border-b border-slate-700/50 pb-3">
                            <h3 class="text-sm font-bold text-[#00f0ff] uppercase tracking-widest flex items-center gap-3">
                                <span class="w-6 h-6 rounded-full bg-[#00f0ff]/10 border border-[#00f0ff]/30 flex items-center justify-center text-[10px]">1</span> 
                                Select Payment Gateway
                            </h3>
                            <div class="bg-slate-800 text-slate-400 text-[9px] font-bold px-2 py-1 rounded border border-slate-700 uppercase tracking-widest flex items-center gap-1">
                                <i class="fas fa-lock text-green-400"></i> Secure Comm
                            </div>
                        </div>
                        
                        <!-- Selection Grid -->
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-2" id="paymentGrid">
                            <?php foreach($payment_methods as $pm): ?>
                                <div class="payment-card cursor-pointer bg-slate-800/50 border border-slate-600 hover:border-[#00f0ff] rounded-2xl p-5 text-center transition-all duration-300 group/card shadow-inner" 
                                    onclick="selectPayment(<?php echo htmlspecialchars(json_encode($pm)); ?>, this)">
                                    <div class="w-12 h-12 mx-auto rounded-xl bg-slate-900 flex items-center justify-center mb-3 group-hover/card:scale-110 transition-transform duration-300 shadow-sm border border-slate-700 group-hover/card:border-[#00f0ff]/50">
                                        <i class="<?php echo htmlspecialchars($pm['logo_class']); ?> text-xl text-slate-500 group-hover/card:text-[#00f0ff] transition-colors"></i>
                                    </div>
                                    <p class="text-xs font-bold text-slate-300 group-hover/card:text-white tracking-wide"><?php echo htmlspecialchars($pm['bank_name']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- STEP 2: Transfer Terminal & Verification (Revealed on click) -->
                    <div id="step2Container" class="hidden bg-slate-900/80 backdrop-blur-xl p-6 md:p-8 rounded-3xl border border-[#00f0ff]/30 shadow-[0_20px_50px_rgba(0,240,255,0.1)] relative overflow-hidden animate-fade-in-down">
                        <div class="absolute -right-20 -top-20 w-64 h-64 bg-[#00f0ff]/10 rounded-full blur-3xl pointer-events-none"></div>

                        <h3 class="text-sm font-bold text-[#00f0ff] uppercase tracking-widest mb-6 flex items-center gap-3 border-b border-slate-700/50 pb-3 relative z-10">
                            <span class="w-6 h-6 rounded-full bg-[#00f0ff]/10 border border-[#00f0ff]/30 flex items-center justify-center text-[10px]">2</span> 
                            Complete Transfer Protocol
                        </h3>

                        <!-- MANUAL PAYMENT DETAILS -->
                        <div id="manualPaymentDetails" class="relative z-10">
                            <div class="bg-black/50 border border-slate-700 rounded-2xl p-6 mb-8 shadow-inner flex flex-col md:flex-row justify-between items-center gap-6">
                                <div class="flex-1 w-full space-y-4">
                                    <div>
                                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mb-1">Transfer Exactly</p>
                                        <p class="text-4xl font-black text-[#00f0ff] tracking-tight drop-shadow-[0_0_10px_rgba(0,240,255,0.4)]" id="transferAmountDisplay"><?php echo format_price($final_price); ?></p>
                                    </div>
                                    
                                    <div class="space-y-1 bg-slate-900 p-3 rounded-xl border border-slate-700">
                                        <p class="text-xs text-slate-400 font-medium">Receiver Name: <strong class="text-white ml-1" id="receiverName">...</strong></p>
                                        <div class="flex items-center justify-between gap-3">
                                            <code class="text-lg md:text-xl font-mono text-green-400 font-bold select-all break-all" id="accountNumber">...</code>
                                            <button type="button" onclick="copyAccountInfo()" class="text-slate-500 hover:text-white bg-slate-800 p-2 rounded-lg transition shrink-0 border border-slate-600 shadow-sm" title="Copy Number">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- 5 Minute Session Timer -->
                                <div class="bg-red-900/10 border border-red-500/30 p-5 rounded-xl flex flex-col items-center justify-center shrink-0 w-full md:w-48 shadow-inner">
                                    <i class="fas fa-satellite-dish text-red-500 text-2xl mb-2 animate-pulse"></i>
                                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mb-1 text-center">Session Expires</span>
                                    <span id="sessionTimer" class="font-mono text-3xl font-black text-white tracking-widest drop-shadow-[0_0_8px_rgba(239,68,68,0.5)]">05:00</span>
                                </div>
                            </div>

                            <!-- Verification Inputs -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="input-wrapper">
                                    <input type="text" name="txn_id" id="txn_id" placeholder=" " maxlength="6" pattern="\d{6}"
                                        class="w-full bg-slate-950/50 border-2 border-slate-600 rounded-xl px-4 py-5 text-white font-mono tracking-[0.5em] text-xl focus:border-[#00f0ff] outline-none transition peer shadow-inner text-center">
                                    <label for="txn_id" class="absolute left-4 top-5 text-slate-400 text-sm font-bold tracking-wider transition-all duration-200 pointer-events-none">Last 6 Digits of Txn ID</label>
                                </div>

                                <div id="uploadWrapper" class="relative border-2 border-dashed border-slate-600 rounded-xl h-full min-h-[88px] text-center hover:bg-slate-800/50 hover:border-[#00f0ff] transition-all cursor-pointer group/upload flex flex-col justify-center bg-slate-950/30">
                                    <input type="file" name="proof" id="proofInput" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" 
                                        onchange="document.getElementById('fileNameDisplay').innerHTML = `<span class='text-green-400 font-bold flex items-center justify-center gap-2'><i class='fas fa-check-circle'></i> ` + this.files[0].name + `</span>`; this.parentElement.classList.add('border-green-500/50', 'bg-green-500/10');">
                                    <div class="flex items-center justify-center gap-3 px-4">
                                        <i class="fas fa-cloud-upload-alt text-2xl text-slate-500 group-hover/upload:text-[#00f0ff] transition transform group-hover/upload:-translate-y-1 shrink-0"></i>
                                        <div class="text-left overflow-hidden">
                                            <p class="text-xs font-bold text-slate-300 truncate" id="fileNameDisplay">Upload Payment Receipt</p>
                                            <p class="text-[9px] text-slate-500 font-mono mt-0.5 uppercase">JPG, PNG (Max 5MB)</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- STEP 3: Form Fields (If applicable) -->
                    <?php if($product['delivery_type'] == 'form' && $product['form_fields']): ?>
                    <div class="bg-slate-900/80 backdrop-blur-xl p-6 md:p-8 rounded-3xl border border-yellow-500/30 shadow-xl relative overflow-hidden">
                        <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PHBhdHRlcm4gaWQ9ImdyaWQiIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCIgcGF0dGVyblVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+PHBhdGggZD0iTSAwIDEwIEwgNDAgMTAgTSAxMCAwIEwgMTAgNDAiIGZpbGw9Im5vbmUiIHN0cm9rZT0icmdiYSgyMzQsIDE3OSwgOCwgMC4wNSkiIHN0cm9rZS13aWR0aD0iMSIvPjwvcGF0dGVybj48L2RlZnM+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0idXJsKCNncmlkKSIvPjwvc3ZnPg==')] opacity-50"></div>
                        <h3 class="text-sm font-bold text-yellow-500 uppercase tracking-widest mb-6 flex items-center gap-3 border-b border-slate-700/50 pb-3 relative z-10">
                            <span class="w-6 h-6 rounded-full bg-yellow-500/10 border border-yellow-500/30 flex items-center justify-center text-[10px]">3</span> 
                            Target Account Details
                        </h3>
                        
                        <div class="space-y-5 relative z-10">
                        <?php 
                            $fields = json_decode($product['form_fields'], true);
                            if(is_array($fields)){
                                foreach($fields as $idx => $f): 
                        ?>
                            <div class="input-wrapper">
                                <input type="<?php echo htmlspecialchars($f['type'] ?? 'text'); ?>" name="form_field[<?php echo htmlspecialchars($f['label']); ?>]" id="ff_<?php echo $idx; ?>" placeholder=" " required 
                                    class="w-full bg-slate-950/80 border-2 border-slate-600 rounded-xl px-4 py-4 text-white focus:border-yellow-400 outline-none shadow-inner transition peer">
                                <label for="ff_<?php echo $idx; ?>" class="absolute left-4 top-4 text-slate-400 text-xs font-bold tracking-wider transition-all duration-200 pointer-events-none">
                                    <?php echo htmlspecialchars($f['label']); ?>
                                </label>
                            </div>
                        <?php 
                                endforeach; 
                            } 
                        ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- STEP 4: Mandatory Instructions -->
                    <?php if(!empty($instructions)): ?>
                    <div class="bg-slate-900/80 backdrop-blur-xl border border-red-500/30 p-6 md:p-8 rounded-3xl relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-1 h-full bg-gradient-to-b from-red-500 to-transparent"></div>
                        <h3 class="text-sm font-bold text-red-400 uppercase tracking-widest mb-5 flex items-center gap-3">
                            <span class="w-6 h-6 rounded-full bg-red-500/10 border border-red-500/30 flex items-center justify-center text-[10px] text-red-400">!</span>
                            Security Protocol Agreement
                        </h3>
                        <div class="space-y-3">
                            <?php foreach($instructions as $ins): ?>
                                <label class="flex items-start gap-4 cursor-pointer group select-none bg-slate-950/50 p-3.5 rounded-xl border border-slate-700/50 hover:bg-slate-800 hover:border-red-500/30 transition">
                                    <div class="relative flex items-center pt-0.5 shrink-0">
                                        <input type="checkbox" name="agreed[]" value="<?php echo $ins['id']; ?>" required class="peer sr-only">
                                        <div class="w-5 h-5 rounded border-2 border-slate-500 bg-slate-900 peer-checked:bg-red-500 peer-checked:border-red-500 transition-colors flex items-center justify-center shadow-inner">
                                            <i class="fas fa-check text-white text-[10px] opacity-0 peer-checked:opacity-100 transition-opacity transform scale-50 peer-checked:scale-100 duration-200"></i>
                                        </div>
                                    </div>
                                    <span class="text-xs sm:text-sm text-slate-300 group-hover:text-white transition leading-relaxed font-medium pt-px"><?php echo htmlspecialchars($ins['instruction_text']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Submit Button -->
                    <button type="submit" id="finalSubmitBtn" disabled class="w-full bg-slate-800 border border-slate-700 text-slate-500 font-black py-5 rounded-2xl text-sm uppercase tracking-widest transition-all duration-300 cursor-not-allowed shadow-inner mt-4 relative overflow-hidden group/btn">
                        <span class="relative z-10 flex items-center justify-center gap-2">
                            <i class="fas fa-lock" id="btnLockIcon"></i> <span id="btnText">Awaiting Payment Selection</span>
                        </span>
                    </button>

                </form>
            <?php endif; ?> <!-- End Cooldown Check -->
        </div>

        <!-- RIGHT: Order Summary Poster Sidebar (4 columns) -->
        <div class="lg:col-span-4">
            <div class="bg-slate-900/80 backdrop-blur-xl rounded-3xl border border-slate-700/80 shadow-[0_20px_50px_rgba(0,0,0,0.5)] lg:sticky lg:top-24 overflow-hidden flex flex-col">
                
                <!-- Cinematic Poster Header -->
                <?php if($display_image): ?>
                <div class="aspect-video w-full relative overflow-hidden border-b border-slate-700 shrink-0 bg-black">
                    <img src="<?php echo $display_image; ?>" class="w-full h-full object-cover animate-pan-image opacity-80 mix-blend-screen" alt="Product Poster">
                    
                    <!-- Bottom Gradient Fade -->
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-900 via-transparent to-transparent"></div>
                    
                    <!-- Top Badges -->
                    <div class="absolute top-4 left-4 right-4 flex justify-between items-start">
                        <span class="bg-black/60 backdrop-blur-md border border-white/10 text-white px-2.5 py-1 rounded text-[9px] font-black uppercase tracking-widest shadow-lg">
                            <?php echo htmlspecialchars($product['cat_name']); ?>
                        </span>
                        <?php if($product['delivery_type'] == 'unique'): ?>
                            <span class="bg-green-500/20 backdrop-blur-md border border-green-500/50 text-green-400 px-2 py-1 rounded text-[9px] font-black uppercase tracking-widest shadow-lg flex items-center gap-1">
                                <i class="fas fa-bolt"></i> Auto
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Floating Title -->
                    <div class="absolute bottom-4 left-4 right-4">
                        <h3 class="text-xl font-black text-white leading-tight drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] line-clamp-2">
                            <?php echo htmlspecialchars($product['name']); ?>
                        </h3>
                    </div>
                </div>
                <?php else: ?>
                <div class="aspect-video w-full relative overflow-hidden border-b border-slate-700 shrink-0 bg-gradient-to-br from-blue-900 to-slate-900 flex items-center justify-center">
                    <i class="fas fa-cube text-6xl text-[#00f0ff] opacity-50 drop-shadow-[0_0_20px_rgba(0,240,255,0.5)]"></i>
                    <h3 class="absolute bottom-4 left-4 right-4 text-xl font-black text-white leading-tight drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] line-clamp-2">
                        <?php echo htmlspecialchars($product['name']); ?>
                    </h3>
                </div>
                <?php endif; ?>
                
                <div class="p-6 md:p-8 flex-1 flex flex-col">
                    
                    <!-- Promo UI -->
                    <div class="mb-6 <?php echo $on_cooldown ? 'opacity-50 pointer-events-none' : ''; ?>">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Transmission Code (Promo)</label>
                        <div class="flex gap-2">
                            <input type="text" id="coupon_input" placeholder="e.g. OMEGA20" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm focus:border-yellow-500 focus:ring-1 focus:ring-yellow-500 outline-none uppercase font-mono tracking-wider transition shadow-inner">
                            <button type="button" onclick="applyCoupon()" class="bg-yellow-600 hover:bg-yellow-500 text-slate-900 px-4 rounded-xl text-xs font-black transition shadow-lg uppercase tracking-wide shrink-0">Patch In</button>
                        </div>
                        <p id="coupon_msg" class="text-[10px] mt-2 hidden flex items-center gap-1.5 font-bold ml-1 tracking-wider"></p>
                    </div>

                    <!-- Pricing Breakdown -->
                    <div class="space-y-4 mb-6 border-y border-slate-700/50 py-5 bg-slate-800/30 -mx-6 px-6 md:-mx-8 md:px-8">
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-400 font-medium">Retail Value</span>
                            <span class="text-white font-mono <?php echo ($sale_savings > 0 || $discount > 0) ? 'line-through decoration-slate-500 opacity-50' : 'font-bold'; ?>">
                                <?php echo format_price($original_price ?? $product['price']); ?>
                            </span>
                        </div>
                        
                        <?php if($sale_savings > 0): ?>
                        <div class="flex justify-between text-sm">
                            <span class="text-red-400 font-bold flex items-center gap-1.5"><i class="fas fa-bolt text-[10px]"></i> Flash Sale</span>
                            <span class="text-red-400 font-mono font-bold">- <?php echo format_price($sale_savings); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if($discount > 0): ?>
                        <div class="flex justify-between text-sm">
                            <span class="text-yellow-400 font-bold flex items-center gap-1.5"><i class="fas fa-crown text-[10px]"></i> Agent Offset (-<?php echo $discount; ?>%)</span>
                            <span class="text-yellow-400 font-mono font-bold">- <?php echo format_price($agent_savings); ?></span>
                        </div>
                        <?php endif; ?>

                        <div id="discount_row" class="flex justify-between text-sm hidden">
                            <span class="text-green-400 font-bold flex items-center gap-1.5"><i class="fas fa-ticket-alt text-[10px]"></i> Promo Applied</span>
                            <span class="text-green-400 font-mono font-bold" id="discount_val">0%</span>
                        </div>
                    </div>

                    <div class="flex justify-between items-end mb-2 relative z-10 mt-auto">
                        <span class="text-slate-400 font-black uppercase text-xs tracking-widest">Total Required</span>
                        <span class="text-3xl font-black text-[#00f0ff] tracking-tighter drop-shadow-[0_0_15px_rgba(0,240,255,0.4)]" id="final_price_display">
                            <?php echo format_price($price_after_agent); ?>
                        </span>
                    </div>
                    
                    <p class="text-center text-[9px] text-slate-500 font-medium mt-6 uppercase tracking-widest flex items-center justify-center gap-2 opacity-80 bg-slate-950/50 py-2 rounded-lg border border-slate-800">
                        <i class="fas fa-shield-check text-green-500 text-sm"></i> Secure Admin Provisioning Route
                    </p>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- JS Logic for Session & Interactions -->
<script>
    // --- 0. COOLDOWN TIMER ENGINE ---
    <?php if($on_cooldown): ?>
        let cdLeft = <?php echo $time_remaining; ?>;
        const cdDisplay = document.getElementById('cooldownDisplay');
        const cdTimer = setInterval(() => {
            if (cdLeft <= 0) {
                clearInterval(cdTimer);
                window.location.reload(); // Unlock matrix
            } else {
                let m = Math.floor(cdLeft / 60);
                let s = cdLeft % 60;
                if(cdDisplay) cdDisplay.innerText = `${m < 10 ? '0' : ''}${m}:${s < 10 ? '0' : ''}${s}`;
                cdLeft--;
            }
        }, 1000);
    <?php endif; ?>


    let currentBasePrice = <?php echo $price_after_agent; ?>; 
    let currentFinalPrice = <?php echo $price_after_agent; ?>;
    
    // Timer Variables
    let sessionTimer;
    let timeLeft = 300; // 5 minutes in seconds

    // Elements
    const pGrid = document.querySelectorAll('.payment-card');
    const panel = document.getElementById('step2Container');
    const inputHiddenId = document.getElementById('hidden_payment_id');
    const submitBtn = document.getElementById('finalSubmitBtn');
    const timerDisplay = document.getElementById('sessionTimer');
    const txnInput = document.getElementById('txn_id');
    const proofInput = document.getElementById('proofInput');

    function selectPayment(data, element) {
        // Highlight selection safely to prevent TypeError
        pGrid.forEach(el => {
            el.classList.remove('border-[#00f0ff]', 'bg-[#00f0ff]/10', 'shadow-[0_0_20px_rgba(0,240,255,0.2)]', 'scale-105');
            el.classList.add('border-slate-600', 'bg-slate-800/50');
            
            const icon = el.querySelector('i');
            if (icon && icon.classList) {
                icon.classList.remove('text-[#00f0ff]');
                icon.classList.add('text-slate-500');
            }
            
            const text = el.querySelector('p');
            if (text && text.classList) {
                text.classList.remove('text-white');
                text.classList.add('text-slate-300');
            }
        });
        
        // Active Element Styling
        if (element && element.classList) {
            element.classList.remove('border-slate-600', 'bg-slate-800/50');
            element.classList.add('border-[#00f0ff]', 'bg-[#00f0ff]/10', 'shadow-[0_0_20px_rgba(0,240,255,0.2)]', 'scale-105');
            
            const ei = element.querySelector('i');
            if (ei && ei.classList) {
                ei.classList.remove('text-slate-500');
                ei.classList.add('text-[#00f0ff]');
            }
            
            const ep = element.querySelector('p');
            if (ep && ep.classList) {
                ep.classList.remove('text-slate-300');
                ep.classList.add('text-white');
            }
        }

        // Update Panel Data
        document.getElementById('receiverName').innerText = data.account_name;
        document.getElementById('accountNumber').innerText = data.account_number;
        inputHiddenId.value = data.id;

        // Reveal Panel & Step 2
        panel.classList.remove('hidden');
        
        // Enforce Inputs
        if (txnInput) txnInput.required = true;
        if (proofInput) proofInput.required = true;

        // Small delay to allow display:block to apply before animating opacity
        setTimeout(() => {
            panel.classList.remove('opacity-50', 'pointer-events-none');
        }, 50);
        
        // Enable Submit Button & Update Styling
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.className = "w-full text-slate-900 font-black py-5 rounded-2xl text-sm uppercase tracking-widest transition-all duration-300 shadow-[0_0_20px_rgba(0,240,255,0.4)] mt-6 relative overflow-hidden group/btn transform active:scale-[0.98]";
            submitBtn.innerHTML = `
                <div class="absolute inset-0 bg-gradient-to-r from-blue-500 via-[#00f0ff] to-blue-500 bg-[length:200%_auto] animate-gradient"></div>
                <span class="relative z-10 flex items-center justify-center gap-2 drop-shadow-md">
                    <i class="fas fa-satellite-dish group-hover/btn:animate-pulse"></i> <span id="btnText">Execute Transfer Protocol</span>
                </span>
            `;
        }

        // Start Timer
        startSessionTimer();
    }

    function startSessionTimer() {
        clearInterval(sessionTimer);
        timeLeft = 300; // Reset to 5 mins
        
        if (timerDisplay) {
            timerDisplay.classList.remove('text-red-500', 'scale-110');
            timerDisplay.classList.add('text-white');
        }
        
        sessionTimer = setInterval(() => {
            if(timeLeft <= 0) {
                clearInterval(sessionTimer);
                // Reject operation visually then reload
                document.body.innerHTML += `
                    <div class="fixed inset-0 bg-slate-950/90 z-[999] flex flex-col items-center justify-center text-center animate-fade-in backdrop-blur-xl">
                        <i class="fas fa-shield-alt text-7xl text-red-500 mb-6 shadow-[0_0_40px_rgba(239,68,68,0.5)] rounded-full bg-red-500/10 p-6"></i>
                        <h2 class="text-4xl font-black text-white mb-3 tracking-tight">Session Terminated</h2>
                        <p class="text-slate-400 font-mono tracking-widest uppercase">Security protocol timeout. Reloading matrix...</p>
                    </div>
                `;
                setTimeout(() => window.location.reload(), 2500);
                return;
            }
            
            let m = Math.floor(timeLeft / 60);
            let s = timeLeft % 60;
            
            if (timerDisplay) {
                timerDisplay.innerText = `${m < 10 ? '0' : ''}${m}:${s < 10 ? '0' : ''}${s}`;
                
                // Visual warning at 1 minute
                if(timeLeft <= 60) {
                    timerDisplay.classList.remove('text-white');
                    timerDisplay.classList.add('text-red-500', 'scale-110', 'transition-transform');
                }
            }
            timeLeft--;
        }, 1000);
    }

    function copyAccountInfo() {
        const accElement = document.getElementById('accountNumber');
        if (!accElement) return;
        
        const text = accElement.innerText;
        navigator.clipboard.writeText(text).then(() => {
            const btn = document.querySelector('#step2Container button i');
            if(btn) {
                btn.className = 'fas fa-check text-green-400';
                setTimeout(() => { btn.className = 'fas fa-copy'; }, 2000);
            }
        });
    }

    function applyCoupon() {
        const codeInput = document.getElementById('coupon_input');
        const msg = document.getElementById('coupon_msg');
        
        if (!codeInput || !msg) return;
        const code = codeInput.value;
        
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
                
                let priceStr = new Intl.NumberFormat().format(currentFinalPrice) + ' Ks';
                
                const finalDisplay = document.getElementById('final_price_display');
                if (finalDisplay) finalDisplay.innerText = priceStr;
                
                const transferDisplay = document.getElementById('transferAmountDisplay');
                if(transferDisplay) transferDisplay.innerText = priceStr;
                
                const discountRow = document.getElementById('discount_row');
                const discountVal = document.getElementById('discount_val');
                
                if (discountRow) discountRow.classList.remove('hidden');
                if (discountVal) discountVal.innerText = `-${discountPct}%`;
                
                const hiddenCoupon = document.getElementById('hidden_coupon_code');
                if (hiddenCoupon) hiddenCoupon.value = code;
                
                codeInput.readOnly = true;
                codeInput.classList.add('opacity-50', 'cursor-not-allowed');
            } else {
                msg.classList.add('text-red-400');
                msg.innerHTML = `<i class="fas fa-times-circle"></i> ${data.message}`;
            }
        });
    }

    // Double-Submit Protection
    function handleSecureSubmit(e) {
        if(submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-80', 'cursor-not-allowed');
            const spanText = submitBtn.querySelector('span span') || document.getElementById('btnText');
            if (spanText) spanText.innerText = "Securing Uplink...";
            const icon = submitBtn.querySelector('i');
            if(icon) icon.className = "fas fa-circle-notch fa-spin";
        }
        return true;
    }
</script>