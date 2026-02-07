<?php
// modules/user/profile.php

// 1. Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) die("Invalid CSRF Token");

    $fullname = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    
    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
    if ($stmt->execute([$fullname, $phone, $_SESSION['user_id']])) {
        $success = "Profile updated successfully.";
    } else {
        $error = "Failed to update profile.";
    }
}

// 2. Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) die("Invalid CSRF Token");

    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    // Verify current
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $hash = $stmt->fetchColumn();

    if (password_verify($current, $hash)) {
        if ($new === $confirm) {
            if (strlen($new) >= 8) {
                $new_hash = password_hash($new, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$new_hash, $_SESSION['user_id']]);
                $success_pw = "Password changed successfully.";
            } else {
                $error_pw = "New password must be at least 8 characters.";
            }
        } else {
            $error_pw = "New passwords do not match.";
        }
    } else {
        $error_pw = "Incorrect current password.";
    }
}

// Fetch User Data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Stats
$total_orders = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
$total_orders->execute([$_SESSION['user_id']]);
$order_count = $total_orders->fetchColumn();
?>

<div class="max-w-4xl mx-auto">
    <h1 class="text-3xl font-bold mb-2">My Profile</h1>
    <p class="text-gray-400 mb-8">Manage your account settings and security.</p>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        
        <!-- Sidebar / Stats -->
        <div class="md:col-span-1 space-y-6">
            <div class="glass p-6 rounded-xl border border-gray-700 text-center">
                <div class="w-20 h-20 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl font-bold text-white shadow-lg">
                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                </div>
                <h3 class="text-xl font-bold"><?php echo htmlspecialchars($user['username']); ?></h3>
                <p class="text-sm text-gray-400 mb-4"><?php echo htmlspecialchars($user['email']); ?></p>
                <div class="flex justify-center gap-2">
                    <span class="bg-gray-800 text-xs px-3 py-1 rounded-full border border-gray-600">
                        <i class="fas fa-shopping-cart mr-1"></i> <?php echo $order_count; ?> Orders
                    </span>
                </div>
            </div>
            
            <div class="glass p-6 rounded-xl border border-gray-700">
                <h4 class="font-bold text-gray-300 mb-3 text-sm uppercase">Account Info</h4>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Member Since</span>
                        <span><?php echo date('M Y', strtotime($user['created_at'])); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Last Login</span>
                        <span><?php echo date('d M, H:i'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Forms -->
        <div class="md:col-span-2 space-y-8">
            
            <!-- Personal Info -->
            <div class="glass p-8 rounded-xl border border-gray-700">
                <h3 class="text-xl font-bold mb-6 flex items-center gap-2">
                    <i class="fas fa-user-edit text-blue-500"></i> Personal Details
                </h3>
                
                <?php if(isset($success)) echo "<div class='bg-green-500/20 text-green-400 p-3 rounded mb-4 text-sm'>$success</div>"; ?>
                <?php if(isset($error)) echo "<div class='bg-red-500/20 text-red-400 p-3 rounded mb-4 text-sm'>$error</div>"; ?>

                <form method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Full Name</label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required class="w-full bg-gray-900 border border-gray-600 rounded p-2.5 focus:border-blue-500 outline-none transition">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Phone Number</label>
                            <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" placeholder="+959..." class="w-full bg-gray-900 border border-gray-600 rounded p-2.5 focus:border-blue-500 outline-none transition">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Email Address</label>
                        <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled class="w-full bg-gray-800 border border-gray-700 rounded p-2.5 text-gray-500 cursor-not-allowed">
                        <p class="text-xs text-gray-600 mt-1">Contact support to change email.</p>
                    </div>
                    
                    <button type="submit" name="update_profile" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded font-bold transition shadow-lg">Save Changes</button>
                </form>
            </div>

            <!-- Security -->
            <div class="glass p-8 rounded-xl border border-gray-700 relative overflow-hidden">
                <div class="absolute top-0 right-0 p-4 opacity-5 pointer-events-none">
                    <i class="fas fa-lock text-9xl"></i>
                </div>
                
                <h3 class="text-xl font-bold mb-6 flex items-center gap-2 relative z-10">
                    <i class="fas fa-shield-alt text-red-500"></i> Security
                </h3>

                <?php if(isset($success_pw)) echo "<div class='bg-green-500/20 text-green-400 p-3 rounded mb-4 text-sm relative z-10'>$success_pw</div>"; ?>
                <?php if(isset($error_pw)) echo "<div class='bg-red-500/20 text-red-400 p-3 rounded mb-4 text-sm relative z-10'>$error_pw</div>"; ?>

                <form method="POST" class="space-y-4 relative z-10">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Current Password</label>
                        <input type="password" name="current_password" required class="w-full bg-gray-900 border border-gray-600 rounded p-2.5 focus:border-red-500 outline-none transition">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">New Password</label>
                            <input type="password" name="new_password" required class="w-full bg-gray-900 border border-gray-600 rounded p-2.5 focus:border-green-500 outline-none transition">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Confirm New</label>
                            <input type="password" name="confirm_password" required class="w-full bg-gray-900 border border-gray-600 rounded p-2.5 focus:border-green-500 outline-none transition">
                        </div>
                    </div>
                    
                    <button type="submit" name="change_password" class="bg-gray-700 hover:bg-gray-600 text-white px-6 py-2 rounded font-bold transition border border-gray-600">Update Password</button>
                </form>
            </div>

        </div>
    </div>
</div>