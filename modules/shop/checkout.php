<?php
// modules/shop/checkout.php

// 1. Get Product ID
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 2. Fetch Product Details
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    echo "<div class='text-center text-red-500 mt-10'>Product not found. <a href='index.php' class='underline'>Go Home</a></div>";
    exit;
}

// 3. Fetch Mandatory Instructions
$stmt = $pdo->prepare("SELECT * FROM product_instructions WHERE product_id = ? ORDER BY id ASC");
$stmt->execute([$product_id]);
$instructions = $stmt->fetchAll();

// 4. Fetch Payment Methods (New Step)
$payment_methods = $pdo->query("SELECT * FROM payment_methods WHERE is_active = 1 ORDER BY id DESC")->fetchAll();

// 5. Calculate Final Price (Agent Discount)
$discount = get_user_discount($_SESSION['user_id']);
$base_price = $product['sale_price'] ?: $product['price'];
$final_price = $base_price * ((100 - $discount) / 100);

$error = '';

// 6. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid Security Token. Please refresh.");
    }

    // Validation: Mandatory Instructions
    $agreed_count = isset($_POST['agreed']) ? count($_POST['agreed']) : 0;
    if ($agreed_count < count($instructions)) {
        $error = "You must accept all mandatory terms and conditions.";
    }
    // Validation: Proof Upload
    elseif (empty($_FILES['proof']['name'])) {
        $error = "Please upload a screenshot of your payment transaction.";
    } 
    else {
        // Handle "Form" Type Data Collection
        $form_data_json = null;
        if ($product['delivery_type'] === 'form' && !empty($_POST['form_field'])) {
            $form_data_json = json_encode($_POST['form_field']);
        }

        // Handle File Upload
        $target_dir = "uploads/proofs/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true); // Ensure dir exists
        
        $file_ext = strtolower(pathinfo($_FILES["proof"]["name"], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (!in_array($file_ext, $allowed_ext)) {
            $error = "Only JPG, JPEG, PNG, and WEBP files are allowed.";
        } else {
            $new_filename = uniqid('proof_') . '.' . $file_ext;
            $target_file = $target_dir . $new_filename;

            if (move_uploaded_file($_FILES["proof"]["tmp_name"], $target_file)) {
                
                $txn_id = trim($_POST['txn_id']);
                $email_type = $_POST['email_type'];
                $delivery_email = ($email_type == 'own') ? $_SESSION['user_email'] : 'Admin Provided';

                try {
                    // Insert Order
                    $sql = "INSERT INTO orders (
                        user_id, product_id, email_delivery_type, delivery_email, 
                        form_data, transaction_last_6, proof_image_path, total_price_paid
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $_SESSION['user_id'], 
                        $product_id, 
                        $email_type, 
                        $delivery_email, 
                        $form_data_json, 
                        $txn_id, 
                        $target_file, 
                        $final_price
                    ]);
                    
                    $order_id = $pdo->lastInsertId();

                    // Send Notifications (Telegram & Email)
                    if (function_exists('send_telegram_alert')) {
                        send_telegram_alert($order_id, $product['name'], $final_price, $_SESSION['user_name'] ?? 'User');
                    }
                    
                    // Optional: Integrate MailService here if configured
                    // $mailer = new MailService();
                    // $mailer->sendOrderConfirmation(...);

                    redirect('index.php?module=user&page=orders&success=ordered');

                } catch (Exception $e) {
                    $error = "Database Error: " . $e->getMessage();
                }

            } else {
                $error = "Failed to upload image. Please try again.";
            }
        }
    }
}
?>

<div class="max-w-4xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Left Column: Product & Payment Info -->
    <div class="lg:col-span-2 space-y-6">
        
        <!-- Product Summary -->
        <div class="glass p-6 rounded-xl border border-gray-700 relative overflow-hidden">
            <div class="absolute top-0 right-0 p-4 opacity-5 pointer-events-none">
                <i class="fas fa-shopping-cart text-9xl text-white"></i>
            </div>
            
            <h2 class="text-2xl font-bold text-white mb-2">Checkout</h2>
            <p class="text-gray-400 text-sm mb-6">Complete your purchase for:</p>
            
            <div class="flex items-center gap-4 mb-4">
                <div class="w-12 h-12 bg-gray-800 rounded-lg flex items-center justify-center text-blue-500 text-xl border border-gray-600">
                    <i class="fas fa-box"></i>
                </div>
                <div>
                    <h3 class="font-bold text-lg text-white"><?php echo htmlspecialchars($product['name']); ?></h3>
                    <div class="flex items-center gap-2 mt-1">
                        <?php if($product['sale_price']): ?>
                            <span class="text-xs text-gray-500 line-through"><?php echo format_price($product['price']); ?></span>
                        <?php endif; ?>
                        <span class="text-xl font-bold text-green-400"><?php echo format_price($final_price); ?></span>
                        <?php if($discount > 0): ?>
                            <span class="text-[10px] bg-yellow-500/20 text-yellow-400 px-2 py-0.5 rounded border border-yellow-500/30">
                                -<?php echo $discount; ?>% Agent
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if($product['user_instruction']): ?>
                <div class="bg-blue-900/20 border border-blue-800 text-blue-200 p-3 rounded-lg text-sm flex gap-3">
                    <i class="fas fa-info-circle mt-0.5"></i>
                    <span><?php echo htmlspecialchars($product['user_instruction']); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Payment Methods Display -->
        <div class="glass p-6 rounded-xl border border-gray-700">
            <h3 class="font-bold text-white mb-4 flex items-center gap-2">
                <i class="fas fa-wallet text-yellow-500"></i> Payment Methods
            </h3>
            
            <?php if(empty($payment_methods)): ?>
                <div class="text-center text-gray-500 text-sm py-4 bg-gray-800 rounded-lg border border-gray-700">
                    No payment methods active. Please contact admin.
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach($payment_methods as $pm): ?>
                        <div class="bg-gray-800 p-4 rounded-lg border border-gray-600 flex items-center gap-4 relative group">
                            <div class="w-10 h-10 rounded-full bg-gray-900 flex items-center justify-center text-blue-400 border border-gray-700">
                                <i class="<?php echo $pm['logo_class']; ?>"></i>
                            </div>
                            <div class="flex-1 overflow-hidden">
                                <p class="text-xs text-gray-400 uppercase font-bold"><?php echo htmlspecialchars($pm['bank_name']); ?></p>
                                <p class="text-sm text-white font-medium truncate"><?php echo htmlspecialchars($pm['account_name']); ?></p>
                                <p class="text-sm font-mono text-green-400 mt-0.5"><?php echo htmlspecialchars($pm['account_number']); ?></p>
                            </div>
                            <button onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($pm['account_number']); ?>'); alert('Copied <?php echo htmlspecialchars($pm['bank_name']); ?> Number!');" 
                                    class="text-gray-500 hover:text-white transition p-2" title="Copy Number">
                                <i class="far fa-copy"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="text-xs text-center text-gray-500 mt-4">
                    Please transfer exactly <strong><?php echo format_price($final_price); ?></strong> to one of the accounts above.
                </p>
            <?php endif; ?>
        </div>

    </div>

    <!-- Right Column: Verification Form -->
    <div class="lg:col-span-1">
        <div class="glass p-6 rounded-xl border border-gray-700 sticky top-24">
            <h3 class="font-bold text-white mb-4 border-b border-gray-700 pb-2">Confirm Payment</h3>
            
            <?php if($error): ?>
                <div class="bg-red-500/20 text-red-300 p-3 rounded-lg mb-4 text-xs border border-red-500/30 flex gap-2">
                    <i class="fas fa-exclamation-triangle mt-0.5"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="space-y-5">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                <!-- Dynamic Form Fields -->
                <?php if($product['delivery_type'] === 'form' && !empty($product['form_fields'])): ?>
                    <div class="space-y-3 p-3 bg-gray-800/50 rounded-lg border border-gray-700">
                        <?php 
                            $fields = json_decode($product['form_fields'], true); 
                            if ($fields) {
                                echo '<p class="text-xs font-bold text-blue-400 uppercase mb-2">Account Info</p>';
                                foreach($fields as $field): 
                        ?>
                            <div>
                                <label class="block text-xs text-gray-400 mb-1"><?php echo htmlspecialchars($field['label']); ?></label>
                                <input type="text" name="form_field[<?php echo htmlspecialchars($field['label']); ?>]" required 
                                       class="w-full bg-gray-900 border border-gray-600 rounded p-2 text-sm text-white focus:border-blue-500 outline-none">
                            </div>
                        <?php endforeach; } ?>
                    </div>
                <?php endif; ?>

                <!-- Delivery Type -->
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-2 uppercase">Delivery To</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="cursor-pointer">
                            <input type="radio" name="email_type" value="own" checked class="peer sr-only">
                            <div class="bg-gray-800 border border-gray-600 p-2 rounded text-center text-xs text-gray-400 peer-checked:bg-blue-600 peer-checked:text-white peer-checked:border-blue-500 transition">
                                My Email
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="email_type" value="admin" class="peer sr-only">
                            <div class="bg-gray-800 border border-gray-600 p-2 rounded text-center text-xs text-gray-400 peer-checked:bg-blue-600 peer-checked:text-white peer-checked:border-blue-500 transition">
                                Admin Account
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Transaction Input -->
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1 uppercase">Transaction ID (Last 6)</label>
                    <input type="text" name="txn_id" maxlength="6" required placeholder="123456" 
                           class="w-full bg-gray-900 border border-gray-600 rounded p-3 text-center font-mono text-lg text-white focus:border-green-500 outline-none tracking-widest uppercase">
                </div>

                <!-- File Upload -->
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1 uppercase">Screenshot</label>
                    <input type="file" name="proof" accept="image/*" required class="w-full text-xs text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-gray-700 file:text-white hover:file:bg-gray-600">
                </div>

                <!-- Checkboxes -->
                <?php if($instructions): ?>
                    <div class="space-y-2 pt-2 border-t border-gray-700">
                        <?php foreach($instructions as $ins): ?>
                            <label class="flex gap-2 items-start cursor-pointer group">
                                <input type="checkbox" name="agreed[]" value="<?php echo $ins['id']; ?>" class="mt-0.5 rounded border-gray-600 bg-gray-900 text-blue-600 focus:ring-blue-500 cursor-pointer">
                                <span class="text-xs text-gray-400 group-hover:text-white transition leading-tight">
                                    <?php echo htmlspecialchars($ins['instruction_text']); ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <button type="submit" class="w-full bg-green-600 hover:bg-green-500 text-white font-bold py-3.5 rounded-xl shadow-lg transition transform active:scale-[0.98] flex items-center justify-center gap-2">
                    <span>Submit Order</span> <i class="fas fa-check"></i>
                </button>
            </form>
        </div>
    </div>
</div>