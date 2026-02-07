<?php
// modules/auth/login.php

// Redirect if already logged in
if (is_logged_in()) redirect('index.php');

$error = '';
$success = '';
$show_verify_modal = false;
$user_email = '';

// Check for registration success (triggers modal)
if (isset($_GET['registered']) && $_GET['registered'] == 1) {
    $success = "Account created successfully! Please verify your email.";
    $show_verify_modal = true;
    $user_email = isset($_GET['email']) ? htmlspecialchars(urldecode($_GET['email'])) : '';
}

// Determine Email Provider for Modal Button Logic
$email_domain = substr(strrchr($user_email, "@"), 1);
$email_link = "mailto:"; // Default
$email_btn_text = "Open Email App";
$email_icon = "fas fa-envelope";

if (strpos($email_domain, 'gmail') !== false) {
    $email_link = "https://mail.google.com/";
    $email_btn_text = "Open Gmail";
    $email_icon = "fab fa-google";
} elseif (strpos($email_domain, 'outlook') !== false || strpos($email_domain, 'hotmail') !== false) {
    $email_link = "https://outlook.live.com/";
    $email_btn_text = "Open Outlook";
    $email_icon = "fab fa-microsoft";
} elseif (strpos($email_domain, 'yahoo') !== false) {
    $email_link = "https://mail.yahoo.com/";
    $email_btn_text = "Open Yahoo Mail";
    $email_icon = "fab fa-yahoo";
}

// Handle Login Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection (Safe check for null values)
    $session_token = $_SESSION['csrf_token'] ?? '';
    $post_token = $_POST['csrf_token'] ?? '';
    
    if (empty($session_token) || !hash_equals($session_token, $post_token)) {
        $error = "Session expired or invalid token. Please refresh the page.";
    } else {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        // Fetch User
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Verify Password
        if ($user && password_verify($password, $user['password'])) {
            
            // 🔒 Enforce Email Verification
            if ($user['is_verified'] == 0) {
                $error = "Please verify your email address before logging in.";
            } else {
                // Set Session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['username'];
                $_SESSION['user_email'] = $user['email'];
                
                // Handle Redirect
                $redirect_url = isset($_GET['redirect']) ? urldecode($_GET['redirect']) : 'index.php';
                header("Location: " . $redirect_url);
                exit;
            }
            
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - DigitalMarketplaceMM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #111827; color: white; font-family: 'Inter', sans-serif; }
        .glass { background: rgba(31, 41, 55, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.08); box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37); }
        .input-group { position: relative; }
        .input-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #9ca3af; transition: color 0.3s; }
        .input-field { padding-left: 2.75rem; transition: all 0.3s ease; }
        .input-field:focus + .input-icon { color: #3b82f6; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4 bg-[url('https://images.unsplash.com/photo-1614853316476-de00d14cb1fc?q=80&w=2070&auto=format&fit=crop')] bg-cover bg-center">
    
    <!-- Dark Overlay -->
    <div class="absolute inset-0 bg-gray-900/90 backdrop-blur-sm"></div>

    <!-- Login Card -->
    <div class="w-full max-w-md glass p-8 rounded-2xl relative z-10 animate-fade-in-down border-t border-gray-700">
        
        <div class="text-center mb-8">
            <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-blue-600/20">
                <i class="fas fa-sign-in-alt text-xl text-white"></i>
            </div>
            <h2 class="text-3xl font-bold tracking-tight">Welcome Back</h2>
            <p class="text-gray-400 mt-2 text-sm">Login to access your products</p>
        </div>
        
        <?php if($error): ?>
            <div class="bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-xl mb-6 text-sm flex items-center gap-3 animate-pulse">
                <i class="fas fa-exclamation-circle text-lg"></i>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <?php if($success && !$show_verify_modal): ?>
            <div class="bg-green-500/10 border border-green-500/20 text-green-400 p-4 rounded-xl mb-6 text-sm flex items-center gap-3">
                <i class="fas fa-check-circle text-lg"></i>
                <span><?php echo $success; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="input-group">
                <input type="email" name="email" placeholder="Email Address" required 
                       class="input-field w-full bg-gray-900/50 border border-gray-600 rounded-xl p-3.5 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none placeholder-gray-500 shadow-inner">
                <i class="fas fa-envelope input-icon"></i>
            </div>

            <div class="input-group">
                <input type="password" name="password" placeholder="Password" required 
                       class="input-field w-full bg-gray-900/50 border border-gray-600 rounded-xl p-3.5 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none placeholder-gray-500 shadow-inner">
                <i class="fas fa-lock input-icon"></i>
            </div>

            <div class="flex justify-between items-center text-sm">
                <label class="flex items-center gap-2 cursor-pointer select-none group">
                    <input type="checkbox" name="remember" class="w-4 h-4 rounded border-gray-600 bg-gray-900 text-blue-600 focus:ring-blue-500 cursor-pointer">
                    <span class="text-gray-400 group-hover:text-gray-300 transition">Remember me</span>
                </label>
                <a href="index.php?module=auth&page=forgot_password" class="text-blue-400 hover:text-blue-300 transition">Forgot Password?</a>
            </div>

            <button class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-blue-900/30 transform transition active:scale-[0.98] mt-2">
                Sign In
            </button>
        </form>

        <div class="mt-8 pt-6 border-t border-gray-700/50 text-center">
            <p class="text-sm text-gray-400">
                Don't have an account? <a href="index.php?module=auth&page=register" class="text-blue-400 font-bold hover:underline hover:text-blue-300 transition">Register now</a>
            </p>
            <div class="mt-4">
                <a href="index.php" class="text-gray-500 hover:text-white text-xs transition flex items-center justify-center gap-2">
                    <i class="fas fa-home"></i> Back to Store
                </a>
            </div>
        </div>
    </div>

    <!-- EMAIL VERIFICATION MODAL POPUP -->
    <?php if($show_verify_modal): ?>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fade-in">
        <div class="bg-gray-800 rounded-2xl max-w-sm w-full p-6 text-center border border-gray-700 shadow-2xl transform scale-100 transition-all">
            <div class="w-16 h-16 bg-green-500/20 rounded-full flex items-center justify-center mx-auto mb-4 animate-bounce">
                <i class="fas fa-paper-plane text-3xl text-green-500"></i>
            </div>
            <h3 class="text-2xl font-bold text-white mb-2">Check your Email!</h3>
            <p class="text-gray-400 text-sm mb-6 leading-relaxed">
                We've sent a verification link to <br> <strong class="text-white"><?php echo $user_email; ?></strong>. <br>Please check your inbox (and spam folder).
            </p>
            
            <div class="space-y-3">
                <a href="<?php echo $email_link; ?>" target="_blank" class="block w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 rounded-xl transition shadow-lg flex items-center justify-center gap-2">
                    <i class="<?php echo $email_icon; ?>"></i> <?php echo $email_btn_text; ?>
                </a>
                <button onclick="this.parentElement.parentElement.parentElement.remove()" class="block w-full bg-gray-700 hover:bg-gray-600 text-gray-300 font-medium py-3 rounded-xl transition">
                    I'll do it later
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

</body>
</html>