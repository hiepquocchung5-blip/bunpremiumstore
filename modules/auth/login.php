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
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['username'];
                        $_SESSION['user_email'] = $user['email'];
                        
                        redirect('index.php?module=user&dashboard');
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

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['username'];
                    $_SESSION['user_email'] = $user['email'];

                    if ($remember) {
                        $params = session_get_cookie_params();
                        setcookie(session_name(), session_id(), time() + (86400 * 30), $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
                    }

                    $redirect_url = isset($_GET['redirect']) ? urldecode($_GET['redirect']) : 'index.php?module=user&page=dashboard';
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
                           <i class="fas fa-paper-plane"></i> Dispatch New Code
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
        <?php if(!empty($google_client_id)): ?>
        <a href="<?php echo $google_login_url; ?>" class="w-full bg-slate-800 border border-slate-600 hover:border-slate-500 hover:bg-slate-700 text-white font-bold py-3.5 px-4 rounded-xl shadow-lg transition transform active:scale-[0.98] flex items-center justify-center gap-3 mb-6 group">
            <img src="https://www.svgrepo.com/show/475656/google-color.svg" class="w-5 h-5 transition-transform group-hover:scale-110" alt="Google">
            <span class="text-sm tracking-wide">Sync with Google</span>
        </a>

        <!-- Divider -->
        <div class="flex items-center gap-4 mb-6">
            <div class="h-px bg-slate-700/80 flex-1"></div>
            <span class="text-[10px] text-slate-500 uppercase font-black tracking-widest">Or standard login</span>
            <div class="h-px bg-slate-700/80 flex-1"></div>
        </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="input-group">
                <input type="email" name="email" placeholder="Secure Comm (Email)" required autocomplete="email"
                       class="input-field w-full bg-slate-900/60 border border-slate-600 rounded-xl py-4 text-white focus:border-[#00f0ff] outline-none placeholder-slate-500 backdrop-blur-sm">
                <i class="fas fa-envelope input-icon"></i>
            </div>

            <div class="input-group">
                <input type="password" name="password" placeholder="Password" required autocomplete="current-password"
                       class="input-field w-full bg-slate-900/60 border border-slate-600 rounded-xl py-4 text-white focus:border-[#00f0ff] outline-none placeholder-slate-500 backdrop-blur-sm">
                <i class="fas fa-lock input-icon"></i>
            </div>

            <div class="flex justify-between items-center px-1">
                <label class="flex items-center gap-2 cursor-pointer select-none group">
                    <div class="relative flex items-center">
                        <input type="checkbox" name="remember" class="w-4 h-4 rounded border-slate-600 bg-slate-800 text-[#00f0ff] focus:ring-[#00f0ff] focus:ring-offset-slate-900 cursor-pointer transition">
                    </div>
                    <span class="text-xs text-slate-400 group-hover:text-white transition font-medium">Keep Connection</span>
                </label>
                <a href="index.php?module=auth&page=forgot_password" class="text-xs text-[#00f0ff] hover:text-white transition font-bold tracking-wide">Recover Key</a>
            </div>

            <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-[#00f0ff] hover:from-blue-500 hover:to-[#00f0ff] text-slate-900 font-black py-4 rounded-xl shadow-[0_0_20px_rgba(0,240,255,0.2)] hover:shadow-[0_0_30px_rgba(0,240,255,0.4)] transform transition active:scale-[0.98] text-sm uppercase tracking-widest mt-2 flex justify-center items-center gap-2">
                <span>Initialize Login</span>
                <i class="fas fa-sign-in-alt"></i>
            </button>
        </form>

        <!-- Footer Links -->
        <div class="mt-8 pt-6 border-t border-slate-700/50 text-center space-y-4">
            <p class="text-sm text-slate-400 font-medium">
                No identity yet? <a href="index.php?module=auth&page=register" class="text-[#00f0ff] font-bold hover:underline ml-1">Deploy New User</a>
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