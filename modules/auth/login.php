<?php
// modules/auth/login.php

// ==========================================================================
// CONFIGURATION: GOOGLE OAUTH
// ==========================================================================
// 1. Go to Google Cloud Console > Credentials
// 2. Create OAuth 2.0 Client ID
// 3. Add Authorized Redirect URI: https://yourdomain.com/index.php?module=auth&page=login
define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URL', BASE_URL . 'index.php?module=auth&page=login');

// ==========================================================================
// LOGIC START
// ==========================================================================

if (is_logged_in()) redirect('index.php');

$error = '';
$success = '';
$show_verify_modal = false;
$user_email = '';

// --- RATE LIMITING (Security) ---
if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = 0;
if (!isset($_SESSION['login_lockout'])) $_SESSION['login_lockout'] = 0;

if ($_SESSION['login_attempts'] >= 5 && time() < $_SESSION['login_lockout']) {
    $remaining = ceil(($_SESSION['login_lockout'] - time()) / 60);
    $error = "Too many failed attempts. Please try again in $remaining minutes.";
} else {
    // Reset if lockout expired
    if (time() > $_SESSION['login_lockout']) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_lockout'] = 0;
    }

    // --- 1. HANDLE GOOGLE OAUTH CALLBACK ---
    if (isset($_GET['code'])) {
        $token_url = 'https://oauth2.googleapis.com/token';
        $params = [
            'code' => $_GET['code'],
            'client_id' => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri' => GOOGLE_REDIRECT_URL,
            'grant_type' => 'authorization_code'
        ];

        // Exchange Code for Token
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $token_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        $token_data = json_decode($response, true);

        if (isset($token_data['access_token'])) {
            // Get User Profile
            $info_url = 'https://www.googleapis.com/oauth2/v3/userinfo';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $info_url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token_data['access_token']]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $user_info = json_decode(curl_exec($ch), true);
            curl_close($ch);

            if (isset($user_info['email'])) {
                $g_email = $user_info['email'];
                $g_name = $user_info['name'];
                $g_id = $user_info['sub'];

                // Check Database
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$g_email]);
                $user = $stmt->fetch();

                if ($user) {
                    // Login Existing User
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['username'];
                    $_SESSION['user_email'] = $user['email'];
                    
                    // Auto-verify if not verified (since Google is trusted)
                    if ($user['is_verified'] == 0) {
                        $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = ?")->execute([$user['id']]);
                    }
                    
                    redirect('index.php');
                } else {
                    // Register New User (Google)
                    $username = strtolower(str_replace(' ', '', $g_name)) . rand(100, 999);
                    $random_pass = bin2hex(random_bytes(10)); // Random secure password
                    $hashed = password_hash($random_pass, PASSWORD_DEFAULT);
                    
                    $stmt = $pdo->prepare("INSERT INTO users (full_name, username, email, password, is_verified) VALUES (?, ?, ?, ?, 1)");
                    if ($stmt->execute([$g_name, $username, $g_email, $hashed])) {
                        $_SESSION['user_id'] = $pdo->lastInsertId();
                        $_SESSION['user_name'] = $username;
                        $_SESSION['user_email'] = $g_email;
                        redirect('index.php');
                    } else {
                        $error = "Failed to create account with Google.";
                    }
                }
            }
        } else {
            $error = "Google Login failed. Please try again.";
        }
    }

    // --- 2. HANDLE STANDARD LOGIN ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $session_token = $_SESSION['csrf_token'] ?? '';
        $post_token = $_POST['csrf_token'] ?? '';
        
        if (empty($session_token) || !hash_equals($session_token, $post_token)) {
            $error = "Session expired. Please refresh.";
        } else {
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $remember = isset($_POST['remember']);

            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if ($user['is_verified'] == 0) {
                    $error = "Please verify your email address.";
                    $show_verify_modal = true; // Show helper modal
                    $user_email = $email;
                } else {
                    // Reset Attempts
                    $_SESSION['login_attempts'] = 0;
                    $_SESSION['login_lockout'] = 0;

                    // Set Session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['username'];
                    $_SESSION['user_email'] = $user['email'];

                    // Remember Me Logic (Extend Session Cookie)
                    if ($remember) {
                        $params = session_get_cookie_params();
                        setcookie(session_name(), session_id(), time() + (86400 * 30), $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
                    }

                    $redirect_url = isset($_GET['redirect']) ? urldecode($_GET['redirect']) : 'index.php';
                    header("Location: " . $redirect_url);
                    exit;
                }
            } else {
                $_SESSION['login_attempts']++;
                if ($_SESSION['login_attempts'] >= 5) {
                    $_SESSION['login_lockout'] = time() + (15 * 60); // 15 min lock
                    $error = "Too many failed attempts. Account locked for 15 minutes.";
                } else {
                    $attempts_left = 5 - $_SESSION['login_attempts'];
                    $error = "Invalid credentials. $attempts_left attempts remaining.";
                }
            }
        }
    }
}

// Helper Logic for Modal (Previous logic reused)
if (isset($_GET['registered']) && $_GET['registered'] == 1) {
    $success = "Account created! Please verify your email.";
    $show_verify_modal = true;
    $user_email = isset($_GET['email']) ? htmlspecialchars(urldecode($_GET['email'])) : '';
}

// Google Auth Link Construction
$google_login_url = "https://accounts.google.com/o/oauth2/v2/auth?response_type=code&client_id=" . GOOGLE_CLIENT_ID . "&redirect_uri=" . urlencode(GOOGLE_REDIRECT_URL) . "&scope=email%20profile";

// Email App Logic
$email_domain = substr(strrchr($user_email, "@"), 1);
$email_link = "mailto:"; 
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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - DigitalMarketplaceMM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #0f172a; color: white; font-family: 'Inter', sans-serif; }
        .glass { background: rgba(30, 41, 59, 0.75); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.08); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
        .input-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; transition: color 0.2s; }
        .input-field { padding-left: 2.75rem; transition: all 0.2s ease; }
        .input-field:focus + .input-icon { color: #60a5fa; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4 bg-[url('https://images.unsplash.com/photo-1620641788421-7a1c342ea42e?q=80&w=1974&auto=format&fit=crop')] bg-cover bg-center">
    
    <div class="absolute inset-0 bg-slate-900/90 backdrop-blur-sm"></div>

    <div class="w-full max-w-md glass p-8 rounded-2xl relative z-10 animate-fade-in-down border-t border-gray-700/50">
        
        <div class="text-center mb-6">
            <h2 class="text-3xl font-bold tracking-tight text-white">Welcome Back</h2>
            <p class="text-slate-400 mt-2 text-sm">Sign in to your account</p>
        </div>
        
        <?php if($error): ?>
            <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-xl mb-6 text-sm flex items-center gap-3">
                <i class="fas fa-exclamation-triangle"></i> <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <?php if($success && !$show_verify_modal): ?>
            <div class="bg-green-500/10 border border-green-500/20 text-green-400 px-4 py-3 rounded-xl mb-6 text-sm flex items-center gap-3">
                <i class="fas fa-check-circle"></i> <span><?php echo $success; ?></span>
            </div>
        <?php endif; ?>

        <!-- Google Login -->
        <a href="<?php echo $google_login_url; ?>" class="w-full bg-white text-gray-900 font-bold py-3 rounded-xl shadow hover:bg-gray-100 transition flex items-center justify-center gap-3 mb-6">
            <img src="https://www.svgrepo.com/show/475656/google-color.svg" class="w-5 h-5" alt="Google">
            <span>Sign in with Google</span>
        </a>

        <div class="flex items-center gap-4 mb-6">
            <div class="h-px bg-slate-700 flex-1"></div>
            <span class="text-xs text-slate-500 uppercase font-bold">Or continue with</span>
            <div class="h-px bg-slate-700 flex-1"></div>
        </div>

        <form method="POST" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="relative">
                <input type="email" name="email" placeholder="Email Address" required 
                       class="input-field w-full bg-slate-900/50 border border-slate-600 rounded-xl p-3.5 text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none placeholder-slate-500 shadow-inner text-sm">
                <i class="fas fa-envelope input-icon text-sm"></i>
            </div>

            <div class="relative">
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

        <div class="mt-8 text-center space-y-4">
            <p class="text-sm text-slate-400">
                New here? <a href="index.php?module=auth&page=register" class="text-blue-400 font-bold hover:text-blue-300 transition">Create an account</a>
            </p>
            <a href="index.php" class="inline-flex items-center gap-2 text-xs text-slate-500 hover:text-white transition">
                <i class="fas fa-arrow-left"></i> Return to Store
            </a>
        </div>
    </div>

    <?php if($show_verify_modal): ?>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md">
        <div class="bg-slate-800 rounded-2xl max-w-sm w-full p-6 text-center border border-slate-700 shadow-2xl">
            <div class="w-16 h-16 bg-green-500/10 rounded-full flex items-center justify-center mx-auto mb-5 ring-1 ring-green-500/30">
                <i class="fas fa-paper-plane text-3xl text-green-500 animate-bounce"></i>
            </div>
            <h3 class="text-xl font-bold text-white mb-2">Check your Email</h3>
            <p class="text-slate-400 text-sm mb-6">Link sent to <span class="text-white font-medium"><?php echo $user_email; ?></span></p>
            <a href="<?php echo $email_link; ?>" target="_blank" class="flex items-center justify-center gap-2 w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 rounded-xl transition text-sm mb-3">
                <i class="<?php echo $email_icon; ?>"></i> <?php echo $email_btn_text; ?>
            </a>
            <button onclick="this.closest('.fixed').remove()" class="text-slate-400 hover:text-white text-sm">Close</button>
        </div>
    </div>
    <?php endif; ?>

</body>
</html>