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
    exit; // Stop execution if no product
}

// 3. Fetch Mandatory Instructions
$stmt = $pdo->prepare("SELECT * FROM product_instructions WHERE product_id = ? ORDER BY id ASC");
$stmt->execute([$product_id]);
$instructions = $stmt->fetchAll();

// 4. Calculate Final Price (Agent Discount)
$discount = get_user_discount($_SESSION['user_id']);
$final_price = $product['price'] * ((100 - $discount) / 100);

$error = '';

// 5. Handle Form Submission
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
            // Encode user inputs into JSON
            $form_data_json = json_encode($_POST['form_field']);
        }

        // Handle File Upload
        $target_dir = "uploads/proofs/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true); // Ensure dir exists
        
        $file_ext = strtolower(pathinfo($_FILES["proof"]["name"], PATHINFO_EXTENSION));
        $new_filename = uniqid('proof_') . '.' . $file_ext;
        $target_file = $target_dir . $new_filename;

        if (move_uploaded_file($_FILES["proof"]["tmp_name"], $target_file)) {
            
            // Prepare Order Data
            $txn_id = trim($_POST['txn_id']);
            $email_type = $_POST['email_type'];
            $delivery_email = ($email_type == 'own') ? $_SESSION['user_email'] : 'Admin Provided'; // Or null

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

                // 🚀 Send Telegram Notification
                send_telegram_alert($order_id, $product['name'], $final_price, $_SESSION['user_name'] ?? 'User');

                // Redirect to Orders Page
                redirect('index.php?module=user&page=orders&success=ordered');

            } catch (Exception $e) {
                $error = "Database Error: " . $e->getMessage();
            }

        } else {
            $error = "Failed to upload payment proof. Please try again.";
        }
    }
}
?>

<div class="max-w-3xl mx-auto glass p-8 rounded-xl border border-gray-700 shadow-2xl">
    <!-- Header -->
    <div class="flex justify-between items-start mb-6 border-b border-gray-700 pb-4">
        <div>
            <h2 class="text-2xl font-bold text-white mb-1">Checkout</h2>
            <p class="text-sm text-gray-400">Item: <span class="text-blue-400 font-bold"><?php echo htmlspecialchars($product['name']); ?></span></p>
        </div>
        <div class="text-right">
            <div class="text-sm text-gray-500">Total Price</div>
            <div class="text-3xl font-bold text-green-400"><?php echo format_price($final_price); ?></div>
            <?php if($discount > 0): ?>
                <div class="text-xs text-yellow-500 font-bold bg-yellow-900/30 px-2 py-1 rounded inline-block mt-1">
                    <i class="fas fa-crown"></i> <?php echo $discount; ?>% Agent Discount
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Error Display -->
    <?php if($error): ?>
        <div class="bg-red-500/20 border border-red-500/50 text-red-200 p-4 rounded-lg mb-6 flex items-center gap-3">
            <i class="fas fa-exclamation-triangle text-xl"></i>
            <span><?php echo $error; ?></span>
        </div>
    <?php endif; ?>

    <!-- Main Instruction / Note -->
    <?php if($product['user_instruction']): ?>
        <div class="bg-blue-900/20 border border-blue-800 text-blue-200 p-4 rounded-lg mb-6 text-sm">
            <i class="fas fa-info-circle mr-2"></i> <?php echo htmlspecialchars($product['user_instruction']); ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

        <!-- 1. Dynamic Form Fields (If Delivery Type = Form) -->
        <?php if($product['delivery_type'] === 'form' && !empty($product['form_fields'])): ?>
            <div class="bg-gray-800/50 p-5 rounded-lg border border-gray-700">
                <h3 class="text-lg font-bold text-blue-400 mb-4 border-b border-gray-700 pb-2">
                    <i class="fas fa-list-alt mr-2"></i> Required Information
                </h3>
                <div class="grid gap-4">
                    <?php 
                        $fields = json_decode($product['form_fields'], true); 
                        if ($fields) {
                            foreach($fields as $field): 
                    ?>
                        <div>
                            <label class="block text-sm text-gray-300 mb-1 font-medium"><?php echo htmlspecialchars($field['label']); ?></label>
                            <input type="text" name="form_field[<?php echo htmlspecialchars($field['label']); ?>]" required 
                                   class="w-full bg-gray-900 border border-gray-600 rounded p-2.5 text-white focus:border-blue-500 focus:outline-none placeholder-gray-600"
                                   placeholder="Enter <?php echo htmlspecialchars($field['label']); ?>">
                        </div>
                    <?php 
                            endforeach; 
                        }
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- 2. Mandatory Checkboxes -->
        <?php if($instructions): ?>
            <div class="bg-gray-800/50 p-5 rounded-lg border border-gray-700">
                <h3 class="text-lg font-bold text-red-400 mb-4 border-b border-gray-700 pb-2">
                    <i class="fas fa-tasks mr-2"></i> Terms & Conditions
                </h3>
                <div class="space-y-3">
                    <?php foreach($instructions as $ins): ?>
                        <label class="flex items-start gap-3 cursor-pointer group hover:bg-gray-800 p-2 rounded transition">
                            <input type="checkbox" name="agreed[]" value="<?php echo $ins['id']; ?>" class="mt-1 w-5 h-5 rounded border-gray-600 bg-gray-900 text-blue-600 focus:ring-blue-500 cursor-pointer">
                            <span class="text-sm text-gray-300 group-hover:text-white transition leading-relaxed">
                                <?php echo htmlspecialchars($ins['instruction_text']); ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- 3. Delivery Method -->
        <div class="space-y-2">
            <label class="block text-sm text-gray-400 font-bold">Delivery Destination</label>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <label class="bg-gray-800 border border-gray-600 p-4 rounded-lg cursor-pointer hover:border-blue-500 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-900/10">
                    <div class="flex items-center gap-3">
                        <input type="radio" name="email_type" value="own" checked class="w-4 h-4 text-blue-600 bg-gray-700 border-gray-500">
                        <div>
                            <div class="font-bold text-white">My Email</div>
                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($_SESSION['user_email'] ?? 'Your registered email'); ?></div>
                        </div>
                    </div>
                </label>
                <label class="bg-gray-800 border border-gray-600 p-4 rounded-lg cursor-pointer hover:border-blue-500 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-900/10">
                    <div class="flex items-center gap-3">
                        <input type="radio" name="email_type" value="admin" class="w-4 h-4 text-blue-600 bg-gray-700 border-gray-500">
                        <div>
                            <div class="font-bold text-white">Admin Provided</div>
                            <div class="text-xs text-gray-500">Credentials sent via Chat</div>
                        </div>
                    </div>
                </label>
            </div>
        </div>

        <!-- 4. Payment & Proof -->
        <div class="bg-gray-800/30 p-5 rounded-lg border border-gray-700 space-y-4">
            <h3 class="font-bold text-white mb-2 border-b border-gray-700 pb-2">Payment Verification</h3>
            
            <div>
                <label class="block text-sm text-gray-400 mb-1">Transaction ID (Last 6 Digits)</label>
                <input type="text" name="txn_id" maxlength="6" required placeholder="e.g. 123456"
                       class="w-full bg-gray-900 border border-gray-600 rounded p-3 font-mono text-lg tracking-widest text-center text-white focus:border-green-500 focus:outline-none uppercase">
            </div>

            <div>
                <label class="block text-sm text-gray-400 mb-1">Upload Payment Screenshot</label>
                <div class="relative border-2 border-dashed border-gray-600 rounded-lg p-6 text-center hover:bg-gray-800 transition hover:border-gray-500">
                    <input type="file" name="proof" accept="image/*" required class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="document.getElementById('file-name').innerText = this.files[0].name">
                    <div class="pointer-events-none">
                        <i class="fas fa-cloud-upload-alt text-3xl text-gray-500 mb-2"></i>
                        <p class="text-sm text-gray-400" id="file-name">Click to select image</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit -->
        <button type="submit" class="w-full bg-gradient-to-r from-green-600 to-green-700 hover:from-green-500 hover:to-green-600 text-white font-bold py-4 rounded-lg shadow-lg shadow-green-900/30 transform transition active:scale-[0.98] flex justify-center items-center gap-2 text-lg">
            <span>Confirm Payment</span> <i class="fas fa-chevron-right"></i>
        </button>
        
        <p class="text-center text-xs text-gray-500 mt-4">
            By clicking confirm, you agree to our <a href="#" class="text-blue-400 hover:underline">Terms of Service</a>.
        </p>
    </form>
</div>