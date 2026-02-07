<?php
// modules/auth/login.php

// 1. Redirect if already logged in
if (is_logged_in()) {
    redirect('index.php');
}

$error = '';
$success = '';
$show_verify_modal = false;
$user_email = '';

// 2. Handle Registration Success (Trigger Modal)
if (isset($_GET['registered']) && $_GET['registered'] == 1) {
    $success = "Account created! We sent a verification link to your email.";
    $show_verify_modal = true;
    $user_email = isset($_GET['email']) ? htmlspecialchars(urldecode($_GET['email'])) : '';
}

// 3. Smart Email Provider Detection (For Modal Button)
$email_domain = substr(strrchr($user_email, "@"), 1);
$email_link = "mailto:"; // Fallback
$email_btn_text = "Open Email App";
$email_icon = "fas fa-envelope";

if (strpos($email_domain, 'gmail') !== false) {
    $email_link = "https://mail.google.com/";
    $email_btn_text = "Open Gmail";
    $email_icon = "fab fa-google";
} elseif (strpos($email_domain, 'outlook') !== false || strpos($email_domain, 'hotmail') !== false || strpos($email_domain, 'live') !== false) {
    $email_link = "https://outlook.live.com/";
    $email_btn_text = "Open Outlook";
    $email_icon = "fab fa-microsoft";
} elseif (strpos($email_domain, 'yahoo') !== false) {
    $email_link = "https://mail.yahoo.com/";
    $email_btn_text = "Open Yahoo Mail";
    $email_icon = "fab fa-yahoo";
} elseif (strpos($email_domain, 'icloud') !== false) {
    $email_link = "https://www.icloud.com/mail";
    $email_btn_text = "Open iCloud Mail";
    $email_icon = "fab fa-apple";
}

// 4. Handle Login Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check (Safe against null types)
    $session_token = $_SESSION['csrf_token'] ?? '';
    $post_token = $_POST['csrf_token'] ?? '';
    
    if (empty($session_token) || !hash_equals($session_token, $post_token)) {
        $error = "Security token expired. Please refresh the page.";
    } else {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        // Check DB
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Validate
        if ($user && password_verify($password, $user['password'])) {
            
            // 🔒 Enforcement: Check if Email is Verified
            if ($user['is_verified'] == 0) {
                $error = "Please verify your email address before logging in.";
                // Re-trigger modal logic if needed, or just show error
                $user_email = $email; 
                $show_verify_modal = true; // Optional: Show modal again so they can find the link
            } else {
                // Success: Create Session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['username'];
                $_SESSION['user_email'] = $user['email'];
                
                // Handle Redirect (Return to previous page or Dashboard)
                $redirect_url = isset($_GET['redirect']) ? urldecode($_GET['redirect']) : 'index.php';
                header("Location: " . $redirect_url);
                exit;
            }
            
        } else {
            $error = "Invalid email address or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - DigitalMarketplaceMM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #0f172a; color: white; font-family: 'Inter', sans-serif; }
        
        /* Glassmorphism Card */
        .glass { 
            background: rgba(30, 41, 59, 0.7); 
            backdrop-filter: blur(16px); 
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08); 
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        /* Input Styling with Icons */
        .input-group { position: relative; }
        .input-icon { 
            position: absolute; 
            left: 1rem; 
            top: 50%; 
            transform: translateY(-50%); 
            color: #94a3b8; /* Slate-400 */
            transition: color 0.2s;
        }
        .input-field { 
            padding-left: 2.75rem; 
            transition: all 0.2s ease;
        }
        .input-field:focus + .input-icon { color: #60a5fa; /* Blue-400 */ }
        
        /* Animations */
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-down { animation: fadeInDown 0.5s ease-out; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4 bg-[url('https://images.unsplash.com/photo-1620641788421-7a1c342ea42e?q=80&w=1974&auto=format&fit=crop')] bg-cover bg-center">
    
    <!-- Dark Overlay -->
    <div class="absolute inset-0 bg-slate-900/90 backdrop-blur-sm"></div>

    <!-- Login Container -->
    <div class="w-full max-w-md glass p-8 rounded-2xl relative z-10 animate-fade-in-down border-t border-gray-700/50">
        
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-600 mb-4 shadow-lg shadow-blue-500/20">
                <i class="fas fa-fingerprint text-2xl text-white"></i>
            </div>
            <h2 class="text-2xl font-bold tracking-tight text-white">Welcome Back</h2>
            <p class="text-slate-400 mt-2 text-sm">Enter your credentials to continue</p>
        </div>
        
        <!-- Error Alert -->
        <?php if($error): ?>
            <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-xl mb-6 text-sm flex items-center gap-3">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <!-- Success Alert (If no modal) -->
        <?php if($success && !$show_verify_modal): ?>
            <div class="bg-green-500/10 border border-green-500/20 text-green-400 px-4 py-3 rounded-xl mb-6 text-sm flex items-center gap-3">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $success; ?></span>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="input-group">
                <input type="email" name="email" placeholder="Email Address" required 
                       class="input-field w-full bg-slate-900/50 border border-slate-600 rounded-xl p-3.5 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none placeholder-slate-500 shadow-inner text-sm">
                <i class="fas fa-envelope input-icon text-sm"></i>
            </div>

            <div class="input-group">
                <input type="password" name="password" placeholder="Password" required 
                       class="input-field w-full bg-slate-900/50 border border-slate-600 rounded-xl p-3.5 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none placeholder-slate-500 shadow-inner text-sm">
                <i class="fas fa-lock input-icon text-sm"></i>
            </div>

            <div class="flex justify-between items-center text-xs text-slate-400 px-1">
                <label class="flex items-center gap-2 cursor-pointer select-none group">
                    <input type="checkbox" name="remember" class="rounded border-slate-600 bg-slate-800 text-blue-600 focus:ring-offset-slate-900 focus:ring-blue-500 cursor-pointer">
                    <span class="group-hover:text-slate-300 transition">Remember me</span>
                </label>
                <a href="index.php?module=auth&page=forgot_password" class="text-blue-400 hover:text-blue-300 transition font-medium">Forgot Password?</a>
            </div>

            <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-blue-900/20 transform transition active:scale-[0.98] text-sm tracking-wide">
                Sign In
            </button>
        </form>

        <!-- Footer Links -->
        <div class="mt-8 pt-6 border-t border-slate-700/50 text-center space-y-4">
            <p class="text-sm text-slate-400">
                New here? <a href="index.php?module=auth&page=register" class="text-blue-400 font-bold hover:text-blue-300 transition">Create an account</a>
            </p>
            <a href="index.php" class="inline-flex items-center gap-2 text-xs text-slate-500 hover:text-white transition">
                <i class="fas fa-arrow-left"></i> Return to Store
            </a>
        </div>
    </div>

    <!-- SMART EMAIL VERIFICATION MODAL -->
    <?php if($show_verify_modal): ?>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md animate-fade-in" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="bg-slate-800 rounded-2xl max-w-sm w-full p-6 text-center border border-slate-700 shadow-2xl transform scale-100 transition-all">
            
            <div class="w-16 h-16 bg-green-500/10 rounded-full flex items-center justify-center mx-auto mb-5 ring-1 ring-green-500/30">
                <i class="fas fa-paper-plane text-3xl text-green-500 animate-bounce"></i>
            </div>
            
            <h3 class="text-xl font-bold text-white mb-2" id="modal-title">Verify your Email</h3>
            
            <p class="text-slate-400 text-sm mb-6 leading-relaxed">
                We've sent a secure link to <br>
                <span class="text-white font-medium bg-slate-700 px-2 py-0.5 rounded text-xs"><?php echo $user_email; ?></span><br>
                Click it to activate your account.
            </p>
            
            <div class="space-y-3">
                <a href="<?php echo $email_link; ?>" target="_blank" class="flex items-center justify-center gap-2 w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 rounded-xl transition shadow-lg text-sm">
                    <i class="<?php echo $email_icon; ?>"></i> <?php echo $email_btn_text; ?>
                </a>
                
                <button onclick="this.closest('.fixed').remove()" class="block w-full bg-transparent hover:bg-slate-700 text-slate-400 hover:text-white font-medium py-3 rounded-xl transition text-sm">
                    I'll verify later
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

</body>
</html>