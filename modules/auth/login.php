<?php
// modules/auth/login.php
// PRODUCTION v5.0 - Google Auth V3, CSRF State Protection & Auto-Provisioning

require_once 'includes/MailService.php';

// 1. Redirect if already logged in
if (is_logged_in()) {
    redirect('index.php');
}

$error = '';
$success = '';
$show_verify_modal = false;
$user_email = '';
$unverified_email_attempt = '';

// 2. Handle Registration Success (Trigger Modal)
if (isset($_GET['registered']) && $_GET['registered'] == 1) {
    $success = "Account created! Please verify your email.";
    $show_verify_modal = true;
    $user_email = isset($_GET['email']) ? htmlspecialchars(urldecode($_GET['email'])) : '';
}

// 3. Smart Email Provider Detection (For Modal Button)
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

// 4. Rate Limiting Logic
if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = 0;
if (!isset($_SESSION['login_lockout'])) $_SESSION['login_lockout'] = 0;

if ($_SESSION['login_attempts'] >= 5 && time() < $_SESSION['login_lockout']) {
    $remaining = ceil(($_SESSION['login_lockout'] - time()) / 60);
    $error = "Too many failed attempts. Please wait $remaining minutes.";
} else {
    // Reset lockout
    if (time() > $_SESSION['login_lockout']) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_lockout'] = 0;
    }

    // --- 5. GOOGLE OAUTH HANDLER V3 ---
    $google_client_id = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '';
    $google_client_secret = defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : '';
    $google_redirect_url = defined('GOOGLE_REDIRECT_URL') ? GOOGLE_REDIRECT_URL : '';
    
    // Generate secure state token to prevent CSRF
    if (empty($_SESSION['g_state'])) {
        $_SESSION['g_state'] = bin2hex(random_bytes(16));
    }
    
    $google_login_url = "https://accounts.google.com/o/oauth2/v2/auth?response_type=code&client_id=" . urlencode($google_client_id) . "&redirect_uri=" . urlencode($google_redirect_url) . "&scope=email%20profile&state=" . $_SESSION['g_state'];

    if (isset($_GET['code']) && !empty($google_client_id)) {
        
        // CSRF State Validation
        if (!isset($_GET['state']) || $_GET['state'] !== $_SESSION['g_state']) {
            $error = "OAuth Security Error: Invalid State Token. Please try again.";
        } else {
            $token_url = 'https://oauth2.googleapis.com/token';
            $params = [
                'code' => $_GET['code'],
                'client_id' => $google_client_id,
                'client_secret' => $google_client_secret,
                'redirect_uri' => $google_redirect_url,
                'grant_type' => 'authorization_code'
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $token_url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); 
            $response = curl_exec($ch);
            
            if(curl_errno($ch)){
                $error = 'Google Login Error: ' . curl_error($ch);
            }
            curl_close($ch);
            
            $token_data = json_decode($response, true);

            if (isset($token_data['access_token'])) {
                $info_url = 'https://www.googleapis.com/oauth2/v3/userinfo';
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $info_url);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token_data['access_token']]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                $user_info = json_decode(curl_exec($ch), true);
                curl_close($ch);

                if (isset($user_info['email'])) {
                    $g_email = filter_var($user_info['email'], FILTER_SANITIZE_EMAIL);
                    $g_name = htmlspecialchars(trim($user_info['name']));
                    
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                    $stmt->execute([$g_email]);
                    $user = $stmt->fetch();

                    if ($user) {
                        // Auto-verify if they logged in via Google
                        if ($user['is_verified'] == 0) {
                            $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = ?")->execute([$user['id']]);
                        }
                        
                        // Log them in
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['username'];
                        $_SESSION['user_email'] = $user['email'];
                        
                        // ⚡️ PERFECT RESUME: Prioritize saved session URL
                        $target = $_SESSION['resume_url'] ?? 'index.php?module=user&page=dashboard';
                        unset($_SESSION['resume_url']); // Clear after use
                        redirect($target);
                    } else {
                        // Auto-Register New User (Google V3)
                        $base_username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $g_name));
                        if (empty($base_username)) $base_username = 'user';
                        $username = $base_username . rand(1000, 9999);
                        
                        $random_pass = bin2hex(random_bytes(6)); // 12 char secure key
                        $hashed = password_hash($random_pass, PASSWORD_DEFAULT);
                        
                        try {
                            $stmt = $pdo->prepare("INSERT INTO users (full_name, username, email, password, is_verified) VALUES (?, ?, ?, ?, 1)");
                            if ($stmt->execute([$g_name, $username, $g_email, $hashed])) {
                                $_SESSION['user_id'] = $pdo->lastInsertId();
                                $_SESSION['user_name'] = $username;
                                $_SESSION['user_email'] = $g_email;
                                
                                // Dispatch Generated Credentials via MailService
                                try {
                                    $mailer = new MailService();
                                    $mailer->sendGoogleAuthPassword($g_email, $g_name, $random_pass);
                                } catch (Exception $e) {
                                    error_log("Google Mail Dispatch Failed: " . $e->getMessage());
                                }

                                redirect('index.php?module=user&page=dashboard');
                            } else {
                                $error = "Failed to deploy account via Google node. Database error.";
                            }
                        } catch(PDOException $e) {
                             $error = "System error during Google deployment protocol.";
                        }
                    }
                } else {
                    $error = "Could not retrieve secure comms (email) from Google.";
                }
            } elseif (isset($token_data['error'])) {
                 $error = "Google Authentication Failed: " . htmlspecialchars($token_data['error_description'] ?? 'Unknown error');
            }
        }
    }

    // --- 6. STANDARD LOGIN HANDLER ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $session_token = $_SESSION['csrf_token'] ?? '';
        $post_token = $_POST['csrf_token'] ?? '';
        
        if (empty($session_token) || !hash_equals($session_token, $post_token)) {
            $error = "Security session expired. Please refresh the matrix and try again.";
        } else {
            $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
            $password = $_POST['password'];
            $remember = isset($_POST['remember']);

            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                
                // 🔒 Check Verification
                if ($user['is_verified'] == 0) {
                    $error = "Your identity has not been verified.";
                    $unverified_email_attempt = $email; 
                } else {
                    // Success
                    $_SESSION['login_attempts'] = 0;
                    $_SESSION['login_lockout'] = 0;

                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['username'];
                    $_SESSION['user_email'] = $user['email'];

                    if ($remember) {
                        $params = session_get_cookie_params();
                        setcookie(session_name(), session_id(), time() + (86400 * 30), $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
                    }

                    // ⚡️ PERFECT RESUME: Prioritize saved session URL over GET parameter for better persistence
                    $redirect_url = $_SESSION['resume_url'] ?? (isset($_GET['redirect']) ? urldecode($_GET['redirect']) : 'index.php?module=user&page=dashboard');
                    unset($_SESSION['resume_url']); // Clear after use
                    
                    if (!preg_match('/^index\.php/', $redirect_url)) {
                        $redirect_url = 'index.php';
                    }
                    
                    header("Location: " . $redirect_url);
                    exit;
                }
            } else {
                $_SESSION['login_attempts']++;
                if ($_SESSION['login_attempts'] >= 5) {
                    $_SESSION['login_lockout'] = time() + (5 * 60); // Lock for 5 mins
                }
                $error = "Invalid secure comms or Password.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $curr_theme ?? 'dark'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login - DigitalMarketplaceMM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --page-bg: #0b0f1a; }
        html[data-theme="light"] { --page-bg: #f8fafc; }
        body { 
            background: var(--page-bg); 
            color: white; 
            font-family: 'Inter', sans-serif; 
            min-height: 100vh;
            min-height: -webkit-fill-available;
        }
        html[data-theme="light"] body { color: #0f172a; }
        html[data-theme="light"] .text-white { color: #0f172a !important; }
        html[data-theme="light"] .bg-slate-900, html[data-theme="light"] .bg-slate-900\/50, html[data-theme="light"] .bg-slate-800\/50 { background-color: rgba(255,255,255,0.9) !important; }
        html[data-theme="light"] .border-white\/10 { border-color: #e2e8f0 !important; }

        .glass { 
            background: rgba(15, 23, 42, 0.85); 
            backdrop-filter: blur(20px); 
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(0, 240, 255, 0.15); 
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 30px rgba(0, 240, 255, 0.05); 
        }
        html[data-theme="light"] .glass {
            background: rgba(255, 255, 255, 0.85);
            border-color: #e2e8f0;
        }

        
        .input-group { position: relative; }
        .input-icon { 
            position: absolute; 
            left: 1.25rem; 
            top: 50%; 
            transform: translateY(-50%); 
            color: #64748b; 
            transition: color 0.3s; 
            font-size: 1.1rem;
        }
        .input-field { 
            padding-left: 3.25rem; 
            padding-right: 1rem;
            transition: all 0.3s ease; 
            font-size: 16px !important; 
        }
        .input-field:focus + .input-icon { color: #00f0ff; }
        .input-field:focus { 
            border-color: #00f0ff; 
            box-shadow: inset 0 0 10px rgba(0, 240, 255, 0.1); 
        }

        @keyframes blob {
            0% { transform: translate(0px, 0px) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
            100% { transform: translate(0px, 0px) scale(1); }
        }
        .animate-blob { animation: blob 7s infinite; }
        .animation-delay-2000 { animation-delay: 2s; }
        .animation-delay-4000 { animation-delay: 4s; }
    </style>
</head>
<body class="flex items-center justify-center relative overflow-x-hidden px-4 py-12 min-h-screen">
    
    <!-- Background Effects -->
    <div class="fixed inset-0 w-full h-full bg-[var(--page-bg)] -z-20"></div>
    <div class="fixed top-0 left-0 w-full h-full -z-10 opacity-30">
        <div class="absolute top-[-10%] left-[-10%] w-[50%] h-[50%] bg-blue-600/20 rounded-full blur-[120px]"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[50%] h-[50%] bg-indigo-600/20 rounded-full blur-[120px]"></div>
    </div>

    <!-- Container -->
    <div class="w-full max-w-md space-y-10 relative z-10">
        
        <!-- Header -->
        <div class="text-center">
            <a href="index.php" class="inline-flex items-center gap-3 mb-8 group">
                <div class="w-12 h-12 bg-blue-600 rounded-2xl flex items-center justify-center text-white shadow-lg transition-transform group-hover:rotate-12">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <span class="text-2xl font-bold text-white">Digital<span class="text-blue-500">MM</span></span>
            </a>
            <h2 class="text-3xl font-bold text-white tracking-tight">Welcome Back</h2>
            <p class="text-slate-500 mt-3">Log in to your account to continue</p>
        </div>
        
        <div class="bg-slate-800/20 rounded-[2.5rem] p-8 md:p-10 border border-white/5 shadow-2xl backdrop-blur-xl">
            <!-- Alerts -->
            <?php if($error): ?>
                <div class="bg-rose-500/10 border border-rose-500/20 text-rose-400 p-4 rounded-2xl mb-8 text-sm flex items-start gap-3">
                    <i class="fas fa-exclamation-circle mt-0.5"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if($success && !$show_verify_modal): ?>
                <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-2xl mb-8 text-sm flex items-start gap-3">
                    <i class="fas fa-check-circle mt-0.5"></i>
                    <span><?php echo $success; ?></span>
                </div>
            <?php endif; ?>

            <!-- Social Login -->
            <?php if(!empty($google_client_id)): ?>
            <a href="<?php echo $google_login_url; ?>" class="w-full bg-white text-black font-bold py-4 px-4 rounded-2xl shadow-lg transition-all hover:bg-slate-100 active:scale-95 flex items-center justify-center gap-3 mb-8">
                <img src="https://www.svgrepo.com/show/475656/google-color.svg" class="w-5 h-5" alt="Google">
                <span class="text-sm">Continue with Google</span>
            </a>

            <div class="flex items-center gap-4 mb-8">
                <div class="h-px bg-white/5 flex-1"></div>
                <span class="text-[10px] text-slate-600 font-bold uppercase tracking-widest">or email</span>
                <div class="h-px bg-white/5 flex-1"></div>
            </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="space-y-2">
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest ml-1">Email Address</label>
                    <div class="relative">
                        <input type="email" name="email" placeholder="name@example.com" required autocomplete="email"
                               class="w-full bg-slate-900/40 border border-white/5 rounded-2xl py-4 pl-12 pr-4 text-white focus:border-blue-500 outline-none transition-all placeholder-slate-600">
                        <i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-slate-600 text-sm"></i>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex justify-between items-center px-1">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Password</label>
                        <a href="index.php?module=auth&page=forgot_password" class="text-[10px] font-bold text-blue-500 hover:text-blue-400 uppercase tracking-widest">Forgot?</a>
                    </div>
                    <div class="relative">
                        <input type="password" name="password" placeholder="••••••••" required autocomplete="current-password"
                               class="w-full bg-slate-900/40 border border-white/5 rounded-2xl py-4 pl-12 pr-4 text-white focus:border-blue-500 outline-none transition-all placeholder-slate-600">
                        <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-600 text-sm"></i>
                    </div>
                </div>

                <div class="flex items-center px-1">
                    <label class="flex items-center gap-3 cursor-pointer group">
                        <input type="checkbox" name="remember" class="w-5 h-5 rounded-lg border-white/10 bg-slate-900 text-blue-600 focus:ring-blue-500 focus:ring-offset-slate-900 transition-all">
                        <span class="text-xs text-slate-500 group-hover:text-slate-300 transition-colors">Remember me</span>
                    </label>
                </div>

                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-4 rounded-2xl shadow-lg shadow-blue-500/20 transition-all active:scale-95 flex justify-center items-center gap-2">
                    <span>Sign In</span>
                    <i class="fas fa-arrow-right text-xs"></i>
                </button>
            </form>
        </div>

        <!-- Footer -->
        <div class="text-center space-y-6">
            <p class="text-sm text-slate-500">
                Don't have an account? <a href="index.php?module=auth&page=register" class="text-blue-500 font-bold hover:underline">Sign up for free</a>
            </p>
            <a href="index.php" class="inline-flex items-center gap-2 text-xs font-bold text-slate-600 hover:text-white transition uppercase tracking-widest">
                <i class="fas fa-home"></i> Back to Home
            </a>
        </div>
    </div>

    <!-- Verification Modal (Success State) -->
    <?php if($show_verify_modal): ?>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-xl animate-fade-in">
        <div class="bg-slate-900 border border-[#00f0ff]/30 rounded-3xl max-w-sm w-full p-8 text-center shadow-[0_0_50px_rgba(0,240,255,0.1)] transform transition-all relative overflow-hidden">
            <div class="absolute top-0 inset-x-0 h-1 bg-gradient-to-r from-transparent via-[#00f0ff] to-transparent"></div>
            
            <div class="w-20 h-20 bg-green-500/10 border border-green-500/30 rounded-full flex items-center justify-center mx-auto mb-6 shadow-[0_0_30px_rgba(34,197,94,0.2)]">
                <i class="fas fa-paper-plane text-4xl text-green-400 animate-bounce"></i>
            </div>
            
            <h3 class="text-2xl font-black text-white mb-2">Verify Identity</h3>
            <p class="text-slate-400 text-sm mb-8 leading-relaxed">
                An authentication link was dispatched to:<br>
                <strong class="text-[#00f0ff] font-mono mt-1 inline-block break-all"><?php echo $user_email; ?></strong>
            </p>
            
            <div class="space-y-3">
                <a href="<?php echo $email_link; ?>" target="_blank" class="flex items-center justify-center gap-2 w-full bg-gradient-to-r from-blue-600 to-[#00f0ff] hover:from-blue-500 hover:to-[#00f0ff] text-slate-900 font-black py-4 rounded-xl transition shadow-lg text-sm tracking-wide uppercase">
                    <i class="<?php echo $email_icon; ?> text-lg"></i> <?php echo $email_btn_text; ?>
                </a>
                <button onclick="this.closest('.fixed').remove()" class="block w-full bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white font-bold py-4 rounded-xl transition text-sm">
                    Dismiss
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

</body>
</html>
