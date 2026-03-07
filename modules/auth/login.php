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

    // --- 5. GOOGLE OAUTH HANDLER ---
    // Make sure GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET are set in your .env
    $google_login_url = "https://accounts.google.com/o/oauth2/v2/auth?response_type=code&client_id=" . urlencode(GOOGLE_CLIENT_ID) . "&redirect_uri=" . urlencode(GOOGLE_REDIRECT_URL) . "&scope=email%20profile";

    if (isset($_GET['code'])) {
        $token_url = 'https://oauth2.googleapis.com/token';
        $params = [
            'code' => $_GET['code'],
            'client_id' => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri' => GOOGLE_REDIRECT_URL,
            'grant_type' => 'authorization_code'
        ];

        // Ensure cURL is configured securely
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $token_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Important for production: Verify SSL
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
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['username'];
                    $_SESSION['user_email'] = $user['email'];
                    
                    redirect('index.php?module=user&page=dashboard');
                } else {
                    // Auto-Register New User (Google)
                    // Generate a safe username
                    $base_username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $g_name));
                    if (empty($base_username)) $base_username = 'user';
                    $username = $base_username . rand(1000, 9999);
                    
                    $random_pass = bin2hex(random_bytes(12));
                    $hashed = password_hash($random_pass, PASSWORD_DEFAULT);
                    
                    try {
                        $stmt = $pdo->prepare("INSERT INTO users (full_name, username, email, password, is_verified) VALUES (?, ?, ?, ?, 1)");
                        if ($stmt->execute([$g_name, $username, $g_email, $hashed])) {
                            $_SESSION['user_id'] = $pdo->lastInsertId();
                            $_SESSION['user_name'] = $username;
                            $_SESSION['user_email'] = $g_email;
                            redirect('index.php?module=user&page=dashboard');
                        } else {
                            $error = "Failed to create account with Google. Database error.";
                        }
                    } catch(PDOException $e) {
                         $error = "System error during Google registration.";
                    }
                }
            } else {
                $error = "Could not retrieve email from Google.";
            }
        } elseif (isset($token_data['error'])) {
             $error = "Google Authentication Failed: " . htmlspecialchars($token_data['error_description'] ?? 'Unknown error');
        }
    }

    // --- 6. STANDARD LOGIN HANDLER ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $session_token = $_SESSION['csrf_token'] ?? '';
        $post_token = $_POST['csrf_token'] ?? '';
        
        if (empty($session_token) || !hash_equals($session_token, $post_token)) {
            $error = "Security session expired. Please refresh the page and try again.";
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
                    $error = "Your email address has not been verified.";
                    $unverified_email_attempt = $email; 
                } else {
                    // Success
                    $_SESSION['login_attempts'] = 0;
                    $_SESSION['login_lockout'] = 0;

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['username'];
                    $_SESSION['user_email'] = $user['email'];

                    if ($remember) {
                        $params = session_get_cookie_params();
                        setcookie(session_name(), session_id(), time() + (86400 * 30), $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
                    }

                    $redirect_url = isset($_GET['redirect']) ? urldecode($_GET['redirect']) : 'index.php?module=user&page=dashboard';
                    // Prevent open redirects
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
                $error = "Invalid email or password.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login - DigitalMarketplaceMM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            background: #0f172a; 
            color: white; 
            font-family: 'Inter', sans-serif; 
            /* Fix mobile viewport height issues */
            min-height: 100vh;
            min-height: -webkit-fill-available;
        }
        .glass { 
            background: rgba(15, 23, 42, 0.85); 
            backdrop-filter: blur(20px); 
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(0, 240, 255, 0.15); 
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 30px rgba(0, 240, 255, 0.05); 
        }
        
        /* Mobile Optimized Inputs */
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
            /* Prevent iOS zoom on focus */
            font-size: 16px !important; 
        }
        .input-field:focus + .input-icon { color: #00f0ff; }
        .input-field:focus { 
            border-color: #00f0ff; 
            box-shadow: inset 0 0 10px rgba(0, 240, 255, 0.1); 
        }

        /* Abstract Background Animations */
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
<body class="flex items-center justify-center relative overflow-hidden px-4 py-8 md:p-4">
    
    <!-- Animated Cyberpunk Background -->
    <div class="fixed inset-0 w-full h-full bg-slate-950 -z-20"></div>
    <div class="fixed top-0 -left-4 w-72 h-72 bg-blue-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-40 animate-blob -z-10"></div>
    <div class="fixed top-0 -right-4 w-72 h-72 bg-purple-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-40 animate-blob animation-delay-2000 -z-10"></div>
    <div class="fixed -bottom-8 left-20 w-72 h-72 bg-[#00f0ff] rounded-full mix-blend-multiply filter blur-[128px] opacity-20 animate-blob animation-delay-4000 -z-10"></div>

    <!-- Main Container -->
    <div class="w-full max-w-md glass p-6 md:p-8 rounded-3xl relative z-10 w-full transform transition-all">
        
        <!-- Header -->
        <div class="text-center mb-8">
            <a href="index.php" class="inline-block mb-4 group">
                <div class="w-16 h-16 bg-slate-900 border border-[#00f0ff]/30 rounded-2xl flex items-center justify-center mx-auto shadow-[0_0_15px_rgba(0,240,255,0.2)] group-hover:shadow-[0_0_25px_rgba(0,240,255,0.4)] transition duration-300">
                    <i class="fas fa-bolt text-3xl text-[#00f0ff]"></i>
                </div>
            </a>
            <h2 class="text-3xl font-black tracking-tight text-white mb-1">Access Portal</h2>
            <p class="text-slate-400 text-sm">Secure entry to DigitalMarketplaceMM</p>
        </div>
        
        <!-- Alerts -->
        <?php if($error): ?>
            <div class="bg-red-900/20 border border-red-500/50 text-red-400 p-4 rounded-xl mb-6 text-sm backdrop-blur-md shadow-lg">
                <div class="flex items-start gap-3">
                    <i class="fas fa-exclamation-triangle text-lg mt-0.5 shrink-0"></i>
                    <span class="font-medium leading-snug"><?php echo htmlspecialchars($error); ?></span>
                </div>
                
                <?php if($unverified_email_attempt): ?>
                    <div class="mt-4 pt-3 border-t border-red-500/30">
                        <a href="index.php?module=auth&page=verify_resend&email=<?php echo urlencode($unverified_email_attempt); ?>" 
                           class="block w-full bg-red-600 hover:bg-red-500 text-white text-center py-3 rounded-lg text-sm font-bold transition shadow-lg flex items-center justify-center gap-2">
                           <i class="fas fa-paper-plane"></i> Send New Code
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if($success && !$show_verify_modal): ?>
            <div class="bg-green-900/20 border border-green-500/50 text-green-400 p-4 rounded-xl mb-6 text-sm flex items-center gap-3 backdrop-blur-md shadow-lg">
                <i class="fas fa-shield-check text-xl shrink-0"></i>
                <span class="font-medium leading-snug"><?php echo $success; ?></span>
            </div>
        <?php endif; ?>

        <!-- Google OAuth Button -->
        <a href="<?php echo $google_login_url; ?>" class="w-full bg-white hover:bg-gray-100 text-slate-900 font-black py-3.5 px-4 rounded-xl shadow-lg transition flex items-center justify-center gap-3 mb-6 group">
            <img src="https://www.svgrepo.com/show/475656/google-color.svg" class="w-6 h-6 transition-transform group-hover:scale-110" alt="Google">
            <span class="text-sm tracking-wide">Continue with Google</span>
        </a>

        <!-- Divider -->
        <div class="flex items-center gap-4 mb-6">
            <div class="h-px bg-slate-700/80 flex-1"></div>
            <span class="text-[10px] text-slate-500 uppercase font-black tracking-widest">Or standard login</span>
            <div class="h-px bg-slate-700/80 flex-1"></div>
        </div>

        <!-- Login Form -->
        <form method="POST" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="input-group">
                <input type="email" name="email" placeholder="Email Address" required autocomplete="email"
                       class="input-field w-full bg-slate-900/60 border border-slate-600 rounded-xl py-4 text-white focus:border-[#00f0ff] outline-none placeholder-slate-500 backdrop-blur-sm">
                <i class="fas fa-envelope input-icon"></i>
            </div>

            <div class="input-group">
                <input type="password" name="password" placeholder="Master Password" required autocomplete="current-password"
                       class="input-field w-full bg-slate-900/60 border border-slate-600 rounded-xl py-4 text-white focus:border-[#00f0ff] outline-none placeholder-slate-500 backdrop-blur-sm">
                <i class="fas fa-lock input-icon"></i>
            </div>

            <div class="flex justify-between items-center px-1">
                <label class="flex items-center gap-2 cursor-pointer select-none group">
                    <div class="relative flex items-center">
                        <input type="checkbox" name="remember" class="w-4 h-4 rounded border-slate-600 bg-slate-800 text-[#00f0ff] focus:ring-[#00f0ff] focus:ring-offset-slate-900 cursor-pointer transition">
                    </div>
                    <span class="text-xs text-slate-400 group-hover:text-white transition font-medium">Remember me</span>
                </label>
                <a href="index.php?module=auth&page=forgot_password" class="text-xs text-[#00f0ff] hover:text-white transition font-bold tracking-wide">Recover Password</a>
            </div>

            <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-[#00f0ff] hover:from-blue-500 hover:to-[#00f0ff] text-slate-900 font-black py-4 rounded-xl shadow-[0_0_20px_rgba(0,240,255,0.2)] hover:shadow-[0_0_30px_rgba(0,240,255,0.4)] transform transition active:scale-[0.98] text-sm uppercase tracking-widest mt-2 flex justify-center items-center gap-2">
                <span>Initiate Login</span>
                <i class="fas fa-sign-in-alt"></i>
            </button>
        </form>

        <!-- Footer Links -->
        <div class="mt-8 pt-6 border-t border-slate-700/50 text-center space-y-4">
            <p class="text-sm text-slate-400 font-medium">
                No account yet? <a href="index.php?module=auth&page=register" class="text-[#00f0ff] font-bold hover:underline ml-1">Deploy New User</a>
            </p>
            <a href="index.php" class="inline-flex items-center gap-2 text-xs text-slate-500 hover:text-white transition group font-bold uppercase tracking-wider">
                <i class="fas fa-home group-hover:-translate-x-1 transition-transform"></i> Return to Hub
            </a>
        </div>
    </div>

    <!-- Verification Modal (Success State) -->
    <?php if($show_verify_modal): ?>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-xl animate-fade-in">
        <div class="bg-slate-900 border border-[#00f0ff]/30 rounded-3xl max-w-sm w-full p-8 text-center shadow-[0_0_50px_rgba(0,240,255,0.1)] transform transition-all relative overflow-hidden">
            <!-- Glow FX -->
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
                <a href="<?php echo $email_link; ?>" target="_blank" class="flex items-center justify-center gap-2 w-full bg-blue-600 hover:bg-[#00f0ff] hover:text-slate-900 text-white font-bold py-4 rounded-xl transition shadow-lg text-sm tracking-wide">
                    <i class="<?php echo $email_icon; ?> text-lg"></i> <?php echo $email_btn_text; ?>
                </a>
                <button onclick="this.closest('.fixed').remove()" class="block w-full bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white font-bold py-4 rounded-xl transition text-sm">
                    Dismiss
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Scroll to Top Button -->
    <button id="scrollToTop" class="fixed bottom-8 right-8 bg-blue-600 hover:bg-blue-500 text-white w-12 h-12 rounded-full shadow-2xl flex items-center justify-center transition-all duration-300 opacity-0 invisible translate-y-10 z-40 hover:scale-110">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- Footer -->
    <footer class="border-t border-gray-800 bg-gray-900 relative z-10 text-sm">
        
        <!-- Newsletter Strip -->
        <div class="border-b border-gray-800 bg-gray-800/30">
            <div class="max-w-7xl mx-auto px-4 py-8 flex flex-col md:flex-row items-center justify-between gap-6">
                <div class="text-center md:text-left">
                    <h3 class="text-lg font-bold text-white mb-1">Stay Updated</h3>
                    <p class="text-gray-400 text-xs">Get the latest game deals and exclusive reseller offers.</p>
                </div>
                <form class="flex w-full md:w-auto gap-2">
                    <input type="email" placeholder="Enter your email" class="bg-gray-900 border border-gray-700 text-white px-4 py-2.5 rounded-lg focus:outline-none focus:border-blue-500 w-full md:w-64 text-sm">
                    <button type="button" class="bg-blue-600 hover:bg-blue-500 text-white px-5 py-2.5 rounded-lg font-bold transition shadow-lg text-sm">Subscribe</button>
                </form>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 py-12">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10 mb-12">
                
                <!-- Brand Info -->
                <div class="space-y-4">
                    <a href="index.php" class="flex items-center gap-2 group w-fit">
                        <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white shadow-lg">
                            <i class="fas fa-shopping-bag text-sm"></i>
                        </div>
                        <span class="font-bold text-lg text-white">Digital<span class="text-blue-500">MM</span></span>
                    </a>
                    <p class="text-gray-500 leading-relaxed text-xs">
                        Myanmar's premier digital marketplace. We provide instant access to global entertainment, software, and premium subscriptions with local payment methods.
                    </p>
                    <div class="flex gap-3 pt-2">
                        <a href="https://t.me/bunpremiumstore" target="_blank" class="w-9 h-9 rounded-lg bg-blue-500/10 flex items-center justify-center text-blue-400 hover:bg-blue-500 hover:text-white transition border border-blue-500/20"><i class="fab fa-telegram-plane"></i></a>
                        <a href="#" class="w-9 h-9 rounded-lg bg-pink-500/10 flex items-center justify-center text-pink-400 hover:bg-pink-500 hover:text-white transition border border-pink-500/20"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="w-9 h-9 rounded-lg bg-slate-700/50 flex items-center justify-center text-white hover:bg-slate-600 transition border border-slate-600"><i class="fab fa-tiktok"></i></a>
                    </div>
                </div>

                <!-- Quick Links -->
                <div>
                    <h4 class="font-bold text-white mb-4 uppercase tracking-wider text-xs">Marketplace</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="index.php?module=shop&page=search" class="hover:text-blue-400 transition flex items-center gap-2"><i class="fas fa-chevron-right text-[10px] text-gray-600"></i> Browse All</a></li>
                        <li><a href="index.php?module=shop&page=category&id=2" class="hover:text-blue-400 transition flex items-center gap-2"><i class="fas fa-chevron-right text-[10px] text-gray-600"></i> Gaming</a></li>
                        <li><a href="index.php?module=shop&page=category&id=1" class="hover:text-blue-400 transition flex items-center gap-2"><i class="fas fa-chevron-right text-[10px] text-gray-600"></i> AI Tools</a></li>
                        <li><a href="index.php?module=user&page=agent" class="text-yellow-500 hover:text-yellow-400 transition flex items-center gap-2 font-bold"><i class="fas fa-crown text-[10px]"></i> Reseller Program</a></li>
                    </ul>
                </div>

                <!-- Support -->
                <div>
                    <h4 class="font-bold text-white mb-4 uppercase tracking-wider text-xs">Customer Care</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="index.php?module=info&page=tutorial" class="hover:text-blue-400 transition flex items-center gap-2"><i class="fas fa-chevron-right text-[10px] text-gray-600"></i> How to Buy</a></li>
                        <li><a href="index.php?module=info&page=support" class="hover:text-blue-400 transition flex items-center gap-2"><i class="fas fa-chevron-right text-[10px] text-gray-600"></i> Help Center</a></li>
                        <li><a href="index.php?module=info&page=terms" class="hover:text-blue-400 transition flex items-center gap-2"><i class="fas fa-chevron-right text-[10px] text-gray-600"></i> Terms & Conditions</a></li>
                        <li><a href="index.php?module=info&page=privacy" class="hover:text-blue-400 transition flex items-center gap-2"><i class="fas fa-chevron-right text-[10px] text-gray-600"></i> Privacy Policy</a></li>
                    </ul>
                </div>

                <!-- Payment -->
                <div>
                    <h4 class="font-bold text-white mb-4 uppercase tracking-wider text-xs">Verified Payments</h4>
                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <div class="bg-gray-800 px-3 py-2 rounded border border-gray-700 flex items-center justify-center opacity-80 hover:opacity-100 transition cursor-pointer">
                            <span class="font-bold text-blue-400">KBZPay</span>
                        </div>
                        <div class="bg-gray-800 px-3 py-2 rounded border border-gray-700 flex items-center justify-center opacity-80 hover:opacity-100 transition cursor-pointer">
                            <span class="font-bold text-yellow-500">Wave</span>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500">
                        <i class="fas fa-lock mr-1 text-green-500"></i> 
                        Payments are manually verified by our team for maximum security.
                    </p>
                </div>
            </div>

            <!-- Bottom Bar -->
            <div class="border-t border-gray-800 pt-8 flex flex-col md:flex-row justify-between items-center gap-4">
                <p class="text-gray-600 text-xs">
                    &copy; <?php echo date('Y'); ?> DigitalMarketplaceMM. All rights reserved.
                </p>
                <div class="flex items-center gap-4 text-xs text-gray-600">
                    <span class="flex items-center gap-1"><i class="fas fa-circle text-green-500 text-[6px]"></i> System Operational</span>
                    <span>v1.5.0</span>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Core JS -->
    <script src="assets/js/app.js"></script>

    <!-- Scroll Top Script -->
    <script>
        const scrollBtn = document.getElementById('scrollToTop');
        
        window.addEventListener('scroll', () => {
            if (window.scrollY > 300) {
                scrollBtn.classList.remove('opacity-0', 'invisible', 'translate-y-10');
            } else {
                scrollBtn.classList.add('opacity-0', 'invisible', 'translate-y-10');
            }
        });
        
        if(scrollBtn) {
            scrollBtn.addEventListener('click', () => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        }

        // Trigger Push Permission Logic (if user clicks the button in header)
        const pushBtn = document.getElementById('enable-push');
        if(pushBtn) {
            pushBtn.addEventListener('click', (e) => {
                e.preventDefault();
                // This function is defined in assets/js/app.js
                if(typeof registerServiceWorker === 'function') {
                    registerServiceWorker().then(() => alert('Notifications Enabled!'));
                } else {
                    alert('Please check browser settings.');
                }
            });
        }
    </script>
</body>
</html>