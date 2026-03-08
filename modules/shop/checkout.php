<?php
// modules/shop/checkout.php
// PRODUCTION DEPLOYMENT v3.6 - Upgraded UI, Sticky Sidebar & Timer Logic

if (!is_logged_in()) redirect('index.php?module=auth&page=login');

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

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
                
                // FORCE ADMIN PROVISIONING
                $email_type = 'admin_provided';
                $delivery = 'Secure Admin Delivery via Chat';
                
                // Get Payment Method Name for JSON Data
                $stmt_pm = $pdo->prepare("SELECT bank_name FROM payment_methods WHERE id = ?");
                $stmt_pm->execute([$selected_payment_id]);
                $pm_name = $stmt_pm->fetchColumn() ?: 'Manual Transfer';

                $form_data_array = ['Payment Node' => $pm_name];
                
                if ($product['delivery_type'] === 'form' && isset($_POST['form_field'])) {
                     $form_data_array = array_merge($form_data_array, $_POST['form_field']);
                }
                $form_data = json_encode($form_data_array);

                // Insert Order
                $sql = "INSERT INTO orders (user_id, product_id, email_delivery_type, delivery_email, form_data, transaction_last_6, proof_image_path, total_price_paid, coupon_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$_SESSION['user_id'], $product_id, $email_type, $delivery, $form_data, $txn_id, $target_file, $final_price, $coupon_code]);
                
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

// Calculate savings for UI
$sale_savings = $product['price'] - $base_price;
$agent_savings = $base_price - $price_after_agent;
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
        <h1 class="text-3xl font-black text-white tracking-tight">Checkout Node</h1>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10">
        
        <!-- LEFT: Checkout Form (8 columns) -->
        <div class="lg:col-span-8 space-y-6">
            
            <?php if(isset($error)) echo "<div class='bg-red-900/20 border border-red-500/50 text-red-400 p-4 rounded-2xl flex items-start gap-3 animate-pulse shadow-lg backdrop-blur-md'><i class='fas fa-exclamation-triangle mt-1'></i> $error</div>"; ?>

            <form method="POST" enctype="multipart/form-data" id="checkoutForm" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="coupon_code" id="hidden_coupon_code">
                <input type="hidden" name="payment_method_id" id="hidden_payment_id" required>

                <!-- STEP 1: Interactive Payment Selection -->
                <div class="bg-slate-900/80 backdrop-blur-xl p-6 md:p-8 rounded-3xl border border-slate-700/50 shadow-[0_10px_30px_rgba(0,0,0,0.3)] relative overflow-hidden group">
                    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-600 via-[#00f0ff] to-blue-600 bg-[length:200%_auto] animate-gradient"></div>
                    
                    <h3 class="text-sm font-bold text-[#00f0ff] uppercase tracking-widest mb-6 flex items-center gap-3 border-b border-slate-700/50 pb-3">
                        <span class="w-6 h-6 rounded-full bg-[#00f0ff]/10 border border-[#00f0ff]/30 flex items-center justify-center text-[10px]">1</span> 
                        Select Payment Node
                    </h3>
                    
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-6" id="paymentGrid">
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

                    <!-- Hidden Details Panel -->
                    <div id="paymentDetailsPanel" class="hidden bg-gradient-to-br from-slate-900 to-slate-800 border border-yellow-500/30 rounded-2xl p-6 relative overflow-hidden animate-fade-in-down shadow-[0_0_20px_rgba(234,179,8,0.1)]">
                        <div class="absolute right-0 top-0 w-40 h-40 bg-yellow-500/10 rounded-full blur-3xl pointer-events-none"></div>
                        
                        <div class="flex flex-col md:flex-row justify-between md:items-end gap-6 relative z-10">
                            <div class="flex-1 space-y-4">
                                <div>
                                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mb-1">Transfer Exactly</p>
                                    <p class="text-3xl font-black text-yellow-400 tracking-tight drop-shadow-[0_0_10px_rgba(234,179,8,0.3)]" id="transferAmountDisplay"><?php echo format_price($final_price); ?></p>
                                </div>
                                
                                <div class="bg-black/30 p-4 rounded-xl border border-slate-700/50">
                                    <div class="flex justify-between items-center mb-1">
                                        <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">Account Data</p>
                                        <button type="button" onclick="copyAccountInfo()" class="text-slate-400 hover:text-white transition group/copy flex items-center gap-1" title="Copy Number">
                                            <i class="fas fa-copy group-hover/copy:scale-110 transition"></i> <span class="text-[10px]">Copy</span>
                                        </button>
                                    </div>
                                    <p class="text-sm text-white font-medium mb-1"><span class="text-slate-500 text-xs mr-1">Name:</span> <span id="receiverName">...</span></p>
                                    <code class="text-xl font-mono text-green-400 font-bold select-all block mt-2" id="accountNumber">...</code>
                                </div>
                            </div>

                            <!-- 5 Minute Session Timer -->
                            <div class="bg-red-900/10 border border-red-500/30 p-4 rounded-xl flex flex-col items-center justify-center shrink-0 w-full md:w-48 shadow-inner">
                                <i class="fas fa-shield-alt text-red-500 text-xl mb-2"></i>
                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mb-1 text-center">Session Expires</span>
                                <span id="sessionTimer" class="font-mono text-3xl font-black text-white tracking-widest drop-shadow-[0_0_8px_rgba(239,68,68,0.5)]">05:00</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 2: Proof & Transaction ID -->
                <div id="step2Container" class="opacity-50 pointer-events-none transition-all duration-300 bg-slate-900/80 backdrop-blur-xl p-6 md:p-8 rounded-3xl border border-slate-700/50 shadow-xl">
                    <h3 class="text-sm font-bold text-[#00f0ff] uppercase tracking-widest mb-6 flex items-center gap-3 border-b border-slate-700/50 pb-3">
                        <span class="w-6 h-6 rounded-full bg-[#00f0ff]/10 border border-[#00f0ff]/30 flex items-center justify-center text-[10px]">2</span> 
                        Submit Verification
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="input-wrapper mt-2">
                            <input type="text" name="txn_id" id="txn_id" placeholder=" " required maxlength="6" pattern="\d{6}"
                                   class="w-full bg-transparent border-2 border-slate-600 rounded-xl px-4 py-4 text-white font-mono tracking-[0.5em] text-xl focus:border-yellow-500 outline-none transition peer">
                            <label for="txn_id" class="absolute left-4 top-4 text-slate-400 text-sm font-bold tracking-wider transition-all duration-200 pointer-events-none">Last 6 Digits of Txn ID</label>
                        </div>

                        <div>
                            <div class="relative border-2 border-dashed border-slate-600 rounded-xl p-5 text-center hover:bg-slate-800/50 hover:border-[#00f0ff] transition-all cursor-pointer group/upload h-full flex flex-col justify-center" id="uploadWrapper">
                                <input type="file" name="proof" id="proofInput" accept="image/*" required class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" 
                                       onchange="document.getElementById('fileNameDisplay').innerHTML = `<span class='text-green-400 font-bold flex items-center justify-center gap-2'><i class='fas fa-check-circle'></i> ` + this.files[0].name + `</span>`; this.parentElement.classList.add('border-green-500/50', 'bg-green-500/10');">
                                <i class="fas fa-cloud-upload-alt text-2xl text-slate-500 mb-2 group-hover/upload:text-[#00f0ff] group-hover/upload:-translate-y-1 transition-all"></i>
                                <p class="text-xs font-bold text-slate-300 truncate px-2" id="fileNameDisplay">Upload Payment Receipt</p>
                                <span class="text-[9px] text-slate-500 font-mono mt-1">JPG/PNG</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 3: Form Fields (If applicable) -->
                <?php if($product['delivery_type'] == 'form' && $product['form_fields']): ?>
                <div class="bg-slate-900/80 backdrop-blur-xl p-6 md:p-8 rounded-3xl border border-blue-500/30 shadow-xl relative overflow-hidden">
                    <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PHBhdHRlcm4gaWQ9ImdyaWQiIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCIgcGF0dGVyblVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+PHBhdGggZD0iTSAwIDEwIEwgNDAgMTAgTSAxMCAwIEwgMTAgNDAiIGZpbGw9Im5vbmUiIHN0cm9rZT0icmdiYSgwLCAyNDAsIDI1NSwgMC4wMykiIHN0cm9rZS13aWR0aD0iMSIvPjwvcGF0dGVybj48L2RlZnM+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0idXJsKCNncmlkKSIvPjwvc3ZnPg==')] opacity-50"></div>
                    <h3 class="text-sm font-bold text-[#00f0ff] uppercase tracking-widest mb-6 flex items-center gap-3 border-b border-slate-700/50 pb-3 relative z-10">
                        <span class="w-6 h-6 rounded-full bg-[#00f0ff]/10 border border-[#00f0ff]/30 flex items-center justify-center text-[10px]">3</span> 
                        Target Details
                    </h3>
                    
                    <div class="space-y-5 relative z-10">
                    <?php 
                        $fields = json_decode($product['form_fields'], true);
                        if(is_array($fields)){
                            foreach($fields as $idx => $f): 
                    ?>
                        <div class="input-wrapper">
                            <input type="<?php echo htmlspecialchars($f['type'] ?? 'text'); ?>" name="form_field[<?php echo htmlspecialchars($f['label']); ?>]" id="ff_<?php echo $idx; ?>" placeholder=" " required 
                                   class="w-full bg-slate-950/50 border-2 border-slate-600 rounded-xl px-4 py-3.5 text-white focus:border-[#00f0ff] outline-none shadow-inner transition peer">
                            <label for="ff_<?php echo $idx; ?>" class="absolute left-4 top-3.5 text-slate-400 text-xs font-bold tracking-wider transition-all duration-200 pointer-events-none">
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
                <div class="bg-red-900/10 border border-red-500/20 p-6 md:p-8 rounded-3xl relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-1 h-full bg-red-500"></div>
                    <h3 class="text-sm font-bold text-red-400 uppercase tracking-widest mb-5 flex items-center gap-2">
                        <i class="fas fa-shield-alt"></i> Security Protocol Agreement
                    </h3>
                    <div class="space-y-3">
                        <?php foreach($instructions as $ins): ?>
                            <label class="flex items-start gap-4 cursor-pointer group select-none bg-slate-900/50 p-3 rounded-xl border border-slate-700/50 hover:bg-slate-800 transition">
                                <div class="relative flex items-center pt-0.5 shrink-0">
                                    <input type="checkbox" name="agreed[]" value="<?php echo $ins['id']; ?>" required class="peer sr-only">
                                    <div class="w-5 h-5 rounded border-2 border-slate-500 bg-slate-900 peer-checked:bg-red-500 peer-checked:border-red-500 transition-colors flex items-center justify-center shadow-inner">
                                        <i class="fas fa-check text-white text-[10px] opacity-0 peer-checked:opacity-100 transition-opacity transform scale-50 peer-checked:scale-100 duration-200"></i>
                                    </div>
                                </div>
                                <span class="text-xs sm:text-sm text-slate-300 group-hover:text-white transition leading-snug font-medium pt-0.5"><?php echo htmlspecialchars($ins['instruction_text']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Submit Button -->
                <button type="submit" id="finalSubmitBtn" disabled class="w-full bg-slate-800 border border-slate-700 text-slate-500 font-black py-5 rounded-2xl text-sm uppercase tracking-widest transition-all duration-300 cursor-not-allowed shadow-inner mt-4 relative overflow-hidden group/btn">
                    <div class="absolute inset-0 bg-gradient-to-r from-blue-600 via-[#00f0ff] to-blue-600 opacity-0 group-hover/btn:opacity-100 transition-opacity duration-300"></div>
                    <span class="relative z-10 flex items-center justify-center gap-2">
                        <i class="fas fa-lock"></i> <span>Awaiting Payment Node Selection</span>
                    </span>
                </button>

            </form>
        </div>

        <!-- RIGHT: Order Summary Sidebar (4 columns) -->
        <div class="lg:col-span-4">
            <div class="bg-slate-900/80 backdrop-blur-xl p-6 rounded-3xl border border-slate-700 shadow-[0_20px_40px_rgba(0,0,0,0.5)] lg:sticky lg:top-24 relative overflow-hidden">
                <!-- Background FX -->
                <div class="absolute -right-20 -bottom-20 w-48 h-48 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>

                <h3 class="font-black text-white text-lg tracking-tight mb-6 border-b border-slate-700/50 pb-4 flex items-center gap-2">
                    <i class="fas fa-receipt text-[#00f0ff]"></i> Order Summary
                </h3>
                
                <!-- Product Mini Card -->
                <div class="flex items-center gap-4 mb-6 bg-slate-800/50 p-3 rounded-2xl border border-slate-700">
                    <div class="w-16 h-16 bg-slate-900 rounded-xl flex items-center justify-center text-[#00f0ff] text-2xl border border-slate-600 shadow-inner shrink-0 overflow-hidden relative">
                        <?php if(!empty($product['image_path'])): ?>
                            <img src="<?php echo BASE_URL . $product['image_path']; ?>" class="w-full h-full object-cover">
                        <?php elseif(!empty($product['cat_image'])): ?>
                            <img src="<?php echo BASE_URL . $product['cat_image']; ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <i class="fas fa-cube opacity-50"></i>
                        <?php endif; ?>
                    </div>
                    <div class="min-w-0">
                        <h4 class="text-sm font-bold text-white leading-tight line-clamp-2"><?php echo htmlspecialchars($product['name']); ?></h4>
                        <span class="text-[9px] uppercase font-bold text-[#00f0ff] bg-[#00f0ff]/10 px-2 py-0.5 rounded border border-[#00f0ff]/20 mt-1.5 inline-block tracking-widest">
                            <?php echo htmlspecialchars($product['delivery_type']); ?> Protocol
                        </span>
                    </div>
                </div>

                <!-- Promo UI -->
                <div class="mb-6">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Transmission Code (Promo)</label>
                    <div class="flex gap-2">
                        <input type="text" id="coupon_input" placeholder="e.g. OMEGA20" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm focus:border-yellow-500 focus:ring-1 focus:ring-yellow-500 outline-none uppercase font-mono tracking-wider transition shadow-inner">
                        <button type="button" onclick="applyCoupon()" class="bg-yellow-600 hover:bg-yellow-500 text-slate-900 px-4 rounded-xl text-xs font-black transition shadow-lg uppercase tracking-wide shrink-0">Apply</button>
                    </div>
                    <p id="coupon_msg" class="text-[10px] mt-2 hidden flex items-center gap-1.5 font-bold ml-1 tracking-wider"></p>
                </div>

                <!-- Pricing Breakdown -->
                <div class="space-y-4 mb-6 border-y border-slate-700/50 py-5 bg-slate-800/30 -mx-6 px-6">
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-400 font-medium">Retail Value</span>
                        <span class="text-white font-mono <?php echo ($sale_savings > 0 || $discount > 0) ? 'line-through decoration-slate-500 opacity-50' : 'font-bold'; ?>">
                            <?php echo format_price($original_price); ?>
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

                <div class="flex justify-between items-end mb-2 relative z-10">
                    <span class="text-slate-400 font-black uppercase text-xs tracking-widest">Total Required</span>
                    <span class="text-3xl font-black text-[#00f0ff] tracking-tighter drop-shadow-[0_0_15px_rgba(0,240,255,0.4)]" id="final_price_display">
                        <?php echo format_price($price_after_agent); ?>
                    </span>
                </div>
                
                <p class="text-center text-[9px] text-slate-500 font-medium mt-6 uppercase tracking-widest flex items-center justify-center gap-1.5 opacity-80">
                    <i class="fas fa-lock text-green-500"></i> Secure 256-bit Encrypted Connection
                </p>
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
            el.classList.remove('border-[#00f0ff]', 'bg-[#00f0ff]/10', 'shadow-[0_0_20px_rgba(0,240,255,0.2)]', 'scale-105');
            el.classList.add('border-slate-600', 'bg-slate-800/50');
            el.querySelector('i').classList.remove('text-[#00f0ff]');
            el.querySelector('i').classList.add('text-slate-500');
            el.querySelector('p').classList.remove('text-white');
            el.querySelector('p').classList.add('text-slate-300');
        });
        
        element.classList.remove('border-slate-600', 'bg-slate-800/50');
        element.classList.add('border-[#00f0ff]', 'bg-[#00f0ff]/10', 'shadow-[0_0_20px_rgba(0,240,255,0.2)]', 'scale-105');
        element.querySelector('i').classList.remove('text-slate-500');
        element.querySelector('i').classList.add('text-[#00f0ff]');
        element.querySelector('p').classList.remove('text-slate-300');
        element.querySelector('p').classList.add('text-white');

        // Update Panel Data
        document.getElementById('receiverName').innerText = data.account_name;
        document.getElementById('accountNumber').innerText = data.account_number;
        inputHiddenId.value = data.id;

        // Reveal Panel & Step 2
        panel.classList.remove('hidden');
        step2.classList.remove('opacity-50', 'pointer-events-none');
        
        // Enable Submit Button & Update Styling
        submitBtn.disabled = false;
        submitBtn.className = "w-full text-slate-900 font-black py-5 rounded-2xl text-sm uppercase tracking-widest transition-all duration-300 shadow-[0_0_20px_rgba(0,240,255,0.4)] mt-6 relative overflow-hidden group/btn transform active:scale-[0.98]";
        submitBtn.innerHTML = `
            <div class="absolute inset-0 bg-gradient-to-r from-blue-500 via-[#00f0ff] to-blue-500 bg-[length:200%_auto] animate-gradient"></div>
            <span class="relative z-10 flex items-center justify-center gap-2 drop-shadow-md">
                <i class="fas fa-satellite-dish group-hover/btn:animate-pulse"></i> <span>Execute Transfer Protocol</span>
            </span>
        `;

        // Start Timer
        startSessionTimer();
    }

    function startSessionTimer() {
        clearInterval(sessionTimer);
        timeLeft = 300; // Reset to 5 mins
        timerDisplay.classList.remove('text-red-500', 'scale-110');
        timerDisplay.classList.add('text-white');
        
        sessionTimer = setInterval(() => {
            if(timeLeft <= 0) {
                clearInterval(sessionTimer);
                // Reject operation visually then reload
                document.body.innerHTML += `
                    <div class="fixed inset-0 bg-slate-950/90 z-[999] flex flex-col items-center justify-center text-center animate-fade-in">
                        <i class="fas fa-shield-alt text-6xl text-red-500 mb-6 shadow-[0_0_30px_rgba(239,68,68,0.5)] rounded-full"></i>
                        <h2 class="text-3xl font-black text-white mb-2">Session Terminated</h2>
                        <p class="text-slate-400 font-mono tracking-widest uppercase">Security protocol timeout. Reloading matrix...</p>
                    </div>
                `;
                setTimeout(() => window.location.reload(), 2500);
                return;
            }
            
            let m = Math.floor(timeLeft / 60);
            let s = timeLeft % 60;
            timerDisplay.innerText = `${m < 10 ? '0' : ''}${m}:${s < 10 ? '0' : ''}${s}`;
            
            // Visual warning at 1 minute
            if(timeLeft <= 60) {
                timerDisplay.classList.remove('text-white');
                timerDisplay.classList.add('text-red-500', 'scale-110', 'transition-transform');
            }
            timeLeft--;
        }, 1000);
    }

    function copyAccountInfo() {
        const text = document.getElementById('accountNumber').innerText;
        navigator.clipboard.writeText(text).then(() => {
            const btn = document.querySelector('#paymentDetailsPanel button i');
            btn.className = 'fas fa-check text-green-400';
            setTimeout(() => { btn.className = 'fas fa-copy'; }, 2000);
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
                
                let priceStr = new Intl.NumberFormat().format(currentFinalPrice) + ' Ks';
                
                document.getElementById('final_price_display').innerText = priceStr;
                
                // Only update transfer amount if payment panel is visible
                const transferDisplay = document.getElementById('transferAmountDisplay');
                if(transferDisplay) transferDisplay.innerText = priceStr;
                
                document.getElementById('discount_row').classList.remove('hidden');
                document.getElementById('discount_val').innerText = `-${discountPct}%`;
                
                document.getElementById('hidden_coupon_code').value = code;
                document.getElementById('coupon_input').readOnly = true;
                document.getElementById('coupon_input').classList.add('opacity-50', 'cursor-not-allowed');
            } else {
                msg.classList.add('text-red-400');
                msg.innerHTML = `<i class="fas fa-times-circle"></i> ${data.message}`;
            }
        });
    }
</script>