<?php
// modules/auth/reset_password.php

// 1. Redirect if already logged in
if (is_logged_in()) redirect('index.php');

$token = isset($_GET['token']) ? $_GET['token'] : '';
$error = '';
$success = '';
$user_id = null;

// 2. Verify Token on Load
if (!$token) {
    die("<div class='flex items-center justify-center min-h-screen bg-gray-900 text-red-400 font-sans'>Invalid request. No token provided. <a href='index.php' class='ml-2 underline'>Go Home</a></div>");
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE verify_token = ?");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    $error = "This password reset link is invalid or has expired.";
} else {
    $user_id = $user['id'];
}

// 3. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id) {
    // CSRF Check
    $session_token = $_SESSION['csrf_token'] ?? '';
    $post_token = $_POST['csrf_token'] ?? '';

    if (empty($session_token) || !hash_equals($session_token, $post_token)) {
        $error = "Security token expired. Please refresh.";
    } else {
        $pass = $_POST['password'];
        $confirm = $_POST['confirm_password'];

        // Validation
        if ($pass !== $confirm) {
            $error = "Passwords do not match.";
        } elseif (strlen($pass) < 8) {
            $error = "Password must be at least 8 characters.";
        } elseif (!preg_match("/[A-Z]/", $pass) || !preg_match("/[0-9]/", $pass)) {
            $error = "Password must contain at least 1 Capital letter and 1 Number.";
        } else {
            // Update Password
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            
            // Clear the token so it can't be used again
            $update = $pdo->prepare("UPDATE users SET password = ?, verify_token = NULL WHERE id = ?");
            
            if ($update->execute([$hash, $user_id])) {
                header("Location: index.php?module=auth&page=login&reset_success=1");
                exit;
            } else {
                $error = "Database error. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Set New Password - DigitalMarketplaceMM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #0f172a; color: white; font-family: 'Inter', sans-serif; }
        .glass { background: rgba(30, 41, 59, 0.75); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.08); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
        .input-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; transition: color 0.2s; }
        .input-field { padding-left: 2.75rem; transition: all 0.2s ease; }
        .input-field:focus + .input-icon { color: #8b5cf6; } /* Violet for Reset Flow */
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4 bg-[url('https://images.unsplash.com/photo-1519681393784-d120267933ba?auto=format&fit=crop&q=80')] bg-cover bg-center">
    
    <div class="absolute inset-0 bg-slate-900/90 backdrop-blur-sm"></div>

    <div class="w-full max-w-md glass p-8 rounded-2xl relative z-10 animate-fade-in-down border-t border-gray-700/50">
        
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br from-violet-600 to-purple-600 mb-4 shadow-lg shadow-purple-500/20">
                <i class="fas fa-lock-open text-2xl text-white"></i>
            </div>
            <h2 class="text-2xl font-bold tracking-tight text-white">New Password</h2>
            <p class="text-slate-400 mt-2 text-sm">Secure your account with a strong password</p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-xl mb-6 text-sm flex items-center gap-3">
                <i class="fas fa-exclamation-triangle text-lg"></i>
                <span><?php echo $error; ?></span>
            </div>
            
            <?php if(strpos($error, 'invalid') !== false): ?>
                <div class="text-center mt-4">
                    <a href="index.php?module=auth&page=forgot_password" class="text-violet-400 hover:text-violet-300 font-bold text-sm">Request a new link</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($user_id): ?>
        <form method="POST" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="relative">
                <input type="password" name="password" placeholder="New Password" required 
                       class="input-field w-full bg-slate-900/50 border border-slate-600 rounded-xl p-3.5 text-white focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none placeholder-slate-500 shadow-inner text-sm">
                <i class="fas fa-lock input-icon text-sm"></i>
            </div>

            <div class="relative">
                <input type="password" name="confirm_password" placeholder="Confirm Password" required 
                       class="input-field w-full bg-slate-900/50 border border-slate-600 rounded-xl p-3.5 text-white focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none placeholder-slate-500 shadow-inner text-sm">
                <i class="fas fa-check-double input-icon text-sm"></i>
            </div>

            <div class="text-[10px] text-slate-500 flex flex-wrap gap-3 px-1 font-medium mt-2">
                <span class="flex items-center gap-1"><i class="fas fa-circle text-[4px]"></i> 8+ Characters</span>
                <span class="flex items-center gap-1"><i class="fas fa-circle text-[4px]"></i> 1 Uppercase</span>
                <span class="flex items-center gap-1"><i class="fas fa-circle text-[4px]"></i> 1 Number</span>
            </div>

            <button type="submit" class="w-full bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-500 hover:to-purple-500 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-purple-900/20 transform transition active:scale-[0.98] text-sm tracking-wide mt-4">
                Update Password
            </button>
        </form>
        <?php endif; ?>

        <div class="mt-8 pt-6 border-t border-slate-700/50 text-center">
            <a href="index.php?module=auth&page=login" class="inline-flex items-center gap-2 text-xs text-slate-500 hover:text-white transition">
                <i class="fas fa-arrow-left"></i> Cancel
            </a>
        </div>
    </div>
</body>
</html>