<?php
// modules/auth/reset_password.php

$token = isset($_GET['token']) ? $_GET['token'] : '';
$error = '';
$success = '';

// 1. Verify Token
if (!$token) {
    die("<div class='text-white text-center mt-20'>Invalid request. No token provided.</div>");
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE verify_token = ?");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    die("<div class='text-white text-center mt-20'>Invalid or expired link. Please try requesting a new one.</div>");
}

// 2. Handle Password Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) die("Invalid CSRF Token");

    $pass = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if ($pass !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($pass) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif (!preg_match("/[A-Z]/", $pass) || !preg_match("/[0-9]/", $pass)) {
        $error = "Password must contain 1 Capital letter and 1 Number.";
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        
        // Update password and clear token so it can't be used again
        $update = $pdo->prepare("UPDATE users SET password = ?, verify_token = NULL WHERE id = ?");
        
        if ($update->execute([$hash, $user['id']])) {
            // Redirect to login with success flag
            // Note: Reuse 'registered=1' for success message or add 'reset=1' logic in login.php
            header("Location: index.php?module=auth&page=login&registered=1");
            exit;
        } else {
            $error = "Database error. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password - DigitalMarketplaceMM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #111827; color: white; font-family: 'Inter', sans-serif; }
        .glass { 
            background: rgba(31, 41, 55, 0.7); 
            backdrop-filter: blur(12px); 
            border: 1px solid rgba(255, 255, 255, 0.08); 
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
        }
        .input-group { position: relative; }
        .input-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #9ca3af; transition: color 0.3s; }
        .input-field { padding-left: 2.75rem; transition: all 0.3s ease; }
        .input-field:focus + .input-icon { color: #3b82f6; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4 bg-[url('https://images.unsplash.com/photo-1519681393784-d120267933ba?auto=format&fit=crop&q=80')] bg-cover bg-center">
    
    <div class="absolute inset-0 bg-gray-900/90 backdrop-blur-sm"></div>

    <div class="w-full max-w-md glass p-8 rounded-2xl shadow-2xl relative z-10">
        <div class="text-center mb-8">
            <div class="w-14 h-14 bg-gradient-to-br from-purple-600 to-blue-600 rounded-xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                <i class="fas fa-key text-2xl text-white"></i>
            </div>
            <h2 class="text-2xl font-bold">Set New Password</h2>
            <p class="text-gray-400 mt-2 text-sm">Choose a strong password for your account</p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-xl mb-6 text-sm flex items-center gap-3">
                <i class="fas fa-exclamation-circle text-lg"></i>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="input-group">
                <input type="password" name="password" placeholder="New Password" required 
                       class="input-field w-full bg-gray-900/50 border border-gray-600 rounded-xl p-3.5 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none placeholder-gray-500">
                <i class="fas fa-lock input-icon"></i>
            </div>

            <div class="input-group">
                <input type="password" name="confirm_password" placeholder="Confirm Password" required 
                       class="input-field w-full bg-gray-900/50 border border-gray-600 rounded-xl p-3.5 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none placeholder-gray-500">
                <i class="fas fa-check-double input-icon"></i>
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3.5 rounded-xl shadow-lg transition transform active:scale-[0.98] mt-4">
                Update Password
            </button>
        </form>
    </div>
</body>
</html>