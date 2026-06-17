<?php
// modules/auth/register.php
// PRODUCTION v4.2 - Google Auth V3 Sync & Terminology Update

// 1. Redirect if already logged in
if (is_logged_in()) {
    redirect('index.php');
}

require_once 'includes/MailService.php';

$error = '';
$success = '';

$form_name = '';
$form_user = '';
$form_email = '';
$form_phone = '';

// Rate Limiting
if (!isset($_SESSION['reg_attempts'])) $_SESSION['reg_attempts'] = 0;
if (!isset($_SESSION['reg_lockout'])) $_SESSION['reg_lockout'] = 0;

if ($_SESSION['reg_attempts'] >= 3 && time() < $_SESSION['reg_lockout']) {
    $remaining = ceil(($_SESSION['reg_lockout'] - time()) / 60);
    $error = "System locked. Too many failed attempts. Please wait $remaining minutes.";
} 
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $form_name = trim($_POST['full_name'] ?? '');
    $form_user = trim($_POST['username'] ?? '');
    $form_email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $form_phone = trim($_POST['phone'] ?? '');
    
    $session_token = $_SESSION['csrf_token'] ?? '';
    $post_token = $_POST['csrf_token'] ?? '';
    $honeypot = $_POST['fax'] ?? '';

    if (!empty($honeypot)) {
        die("System error. Connection terminated.");
    }

    if (empty($session_token) || !hash_equals($session_token, $post_token)) {
        $error = "Security token expired. Please refresh the matrix.";
    } else {
        $password = $_POST['password'];
        $confirm = $_POST['confirm_password'];
        $terms = isset($_POST['terms']);

        if (!filter_var($form_email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format detected.";
        } elseif (!$terms) {
            $error = "You must acknowledge the Security Protocols (Terms).";
        } elseif ($password !== $confirm) {
            $error = "Passwords do not match.";
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters.";
        } elseif (!preg_match("/[A-Z]/", $password) || !preg_match("/[0-9]/", $password)) {
            $error = "Password must contain at least 1 Capital letter and 1 Number.";
        } else {
            $pdo->exec("SET NAMES 'utf8mb4'");

            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$form_email, $form_user]);
            
            if ($stmt->rowCount() > 0) {
                $error = "Identity collision: Username or Email is already active in the network.";
            } else {
                try {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $token = bin2hex(random_bytes(32)); 
                    $phoneVal = empty($form_phone) ? null : $form_phone;

                    $stmt = $pdo->prepare("INSERT INTO users (full_name, username, email, phone, password, verify_token, is_verified) VALUES (?, ?, ?, ?, ?, ?, 0)");
                    
                    if ($stmt->execute([$form_name, $form_user, $form_email, $phoneVal, $hashed, $token])) {
                        try {
                            $mailer = new MailService();
                            $mailer->sendVerificationEmail($form_email, $token);
                        } catch(Exception $e) {
                            error_log("Failed to send verification email: " . $e->getMessage());
                        }

                        $_SESSION['reg_attempts'] = 0;
                        header("Location: index.php?module=auth&page=login&registered=1&email=" . urlencode($form_email));
                        exit;
                    } else {
                        $error = "Deployment failed due to a system error.";
                    }
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) { 
                        $error = "Identity collision: Username or Email already exists.";
                    } elseif ($e->getCode() == 'HY000' && strpos($e->getMessage(), '1366') !== false) {
                        $error = "Invalid characters detected in input. Please use standard ASCII.";
                    } else {
                        $error = "An unexpected error occurred in the matrix. Please try again.";
                    }
                }
            }
        }
        
        if ($error) {
            $_SESSION['reg_attempts']++;
            if ($_SESSION['reg_attempts'] >= 3) {
                $_SESSION['reg_lockout'] = time() + (10 * 60);
            }
        }
    }
}

// --- GOOGLE OAUTH V3 Setup ---
$google_client_id = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '';
$google_redirect_url = defined('GOOGLE_REDIRECT_URL') ? GOOGLE_REDIRECT_URL : '';

if (empty($_SESSION['g_state'])) {
    $_SESSION['g_state'] = bin2hex(random_bytes(16));
}

$google_login_url = "https://accounts.google.com/o/oauth2/v2/auth?response_type=code&client_id=" . urlencode($google_client_id) . "&redirect_uri=" . urlencode($google_redirect_url) . "&scope=email%20profile&state=" . $_SESSION['g_state'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Deploy Account - DigitalMarketplaceMM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
    <style>
        body { 
            background: #0f172a; 
            color: white; 
            font-family: 'Inter', sans-serif; 
            min-height: 100vh;
            -webkit-tap-highlight-color: transparent;
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
            transition: color 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
            font-size: 1.1rem;
        }
        .input-field { 
            padding-left: 3.25rem; 
            padding-right: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
            font-size: 16px !important; 
        }
        .input-field:focus + .input-icon { color: #00f0ff; }
        .input-field:focus { 
            border-color: #00f0ff; 
            box-shadow: inset 0 0 15px rgba(0, 240, 255, 0.15); 
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
        
        .visually-hidden { position: absolute; left: -9999px; top: -9999px; visibility: hidden; }

        .loader-shimmer {
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
        }
        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
    </style>
</head>
<body class="flex items-center justify-center relative overflow-hidden px-4 py-12">
    
    <!-- Background Effects -->
    <div class="fixed inset-0 w-full h-full bg-[#0b0f1a] -z-20"></div>
    <div class="fixed top-0 left-0 w-full h-full -z-10 opacity-30">
        <div class="absolute top-[-10%] left-[-10%] w-[50%] h-[50%] bg-blue-600/20 rounded-full blur-[120px]"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[50%] h-[50%] bg-indigo-600/20 rounded-full blur-[120px]"></div>
    </div>

    <!-- Container -->
    <div class="w-full max-w-2xl space-y-10 relative z-10">
        
        <!-- Header -->
        <div class="text-center">
            <a href="index.php" class="inline-flex items-center gap-3 mb-8 group">
                <div class="w-12 h-12 bg-blue-600 rounded-2xl flex items-center justify-center text-white shadow-lg transition-transform group-hover:rotate-12">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <span class="text-2xl font-bold text-white">Digital<span class="text-blue-500">MM</span></span>
            </a>
            <h2 class="text-3xl font-bold text-white tracking-tight">Create Account</h2>
            <p class="text-slate-500 mt-3">Join our community and start shopping</p>
        </div>
        
        <div class="bg-slate-800/20 rounded-[2.5rem] p-8 md:p-12 border border-white/5 shadow-2xl backdrop-blur-xl">
            <!-- Alerts -->
            <?php if($error): ?>
                <div class="bg-rose-500/10 border border-rose-500/20 text-rose-400 p-4 rounded-2xl mb-8 text-sm flex items-start gap-3">
                    <i class="fas fa-exclamation-circle mt-0.5"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <!-- Social Login -->
            <?php if(!empty($google_client_id)): ?>
            <a href="<?php echo $google_login_url; ?>" class="w-full bg-white text-black font-bold py-4 px-4 rounded-2xl shadow-lg transition-all hover:bg-slate-100 active:scale-95 flex items-center justify-center gap-3 mb-10">
                <img src="https://www.svgrepo.com/show/475656/google-color.svg" class="w-5 h-5" alt="Google">
                <span class="text-sm">Sign up with Google</span>
            </a>

            <div class="flex items-center gap-4 mb-10">
                <div class="h-px bg-white/5 flex-1"></div>
                <span class="text-[10px] text-slate-600 font-bold uppercase tracking-widest">or use email</span>
                <div class="h-px bg-white/5 flex-1"></div>
            </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" id="registerForm" class="space-y-8">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="text" name="fax" class="hidden" tabindex="-1" autocomplete="off">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest ml-1">Full Name</label>
                        <input type="text" name="full_name" placeholder="John Doe" required value="<?php echo htmlspecialchars($form_name); ?>"
                               class="w-full bg-slate-900/40 border border-white/5 rounded-2xl py-4 px-6 text-white focus:border-blue-500 outline-none transition-all placeholder-slate-600">
                    </div>
                    <div class="space-y-2">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest ml-1">Username</label>
                        <input type="text" name="username" placeholder="johndoe" required value="<?php echo htmlspecialchars($form_user); ?>"
                               class="w-full bg-slate-900/40 border border-white/5 rounded-2xl py-4 px-6 text-white focus:border-blue-500 outline-none transition-all placeholder-slate-600">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest ml-1">Email Address</label>
                        <input type="email" name="email" placeholder="name@example.com" required value="<?php echo htmlspecialchars($form_email); ?>"
                               class="w-full bg-slate-900/40 border border-white/5 rounded-2xl py-4 px-6 text-white focus:border-blue-500 outline-none transition-all placeholder-slate-600">
                    </div>
                    <div class="space-y-2">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest ml-1">Phone Number (Optional)</label>
                        <input type="tel" name="phone" placeholder="09xxxxxxx" value="<?php echo htmlspecialchars($form_phone); ?>"
                               class="w-full bg-slate-900/40 border border-white/5 rounded-2xl py-4 px-6 text-white focus:border-blue-500 outline-none transition-all placeholder-slate-600">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2 relative">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest ml-1">Password</label>
                        <input type="password" name="password" id="password" placeholder="••••••••" required 
                               class="w-full bg-slate-900/40 border border-white/5 rounded-2xl py-4 px-6 text-white focus:border-blue-500 outline-none transition-all placeholder-slate-600">
                        <button type="button" id="togglePassword" class="absolute right-4 top-11 text-slate-600 hover:text-blue-500 transition-colors">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="space-y-2 relative">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest ml-1">Confirm Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="••••••••" required 
                               class="w-full bg-slate-900/40 border border-white/5 rounded-2xl py-4 px-6 text-white focus:border-blue-500 outline-none transition-all placeholder-slate-600">
                        <button type="button" id="toggleConfirmPassword" class="absolute right-4 top-11 text-slate-600 hover:text-blue-500 transition-colors">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Strength -->
                <div class="bg-slate-900/50 rounded-2xl p-5 border border-white/5 space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Strength</span>
                        <span id="strengthText" class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Weak</span>
                    </div>
                    <div class="flex gap-2 h-1.5">
                        <div id="bar1" class="flex-1 rounded-full bg-slate-800 transition-all duration-500"></div>
                        <div id="bar2" class="flex-1 rounded-full bg-slate-800 transition-all duration-500"></div>
                        <div id="bar3" class="flex-1 rounded-full bg-slate-800 transition-all duration-500"></div>
                    </div>
                </div>

                <label class="flex items-start gap-4 cursor-pointer group select-none">
                    <div class="relative flex items-center pt-0.5">
                        <input type="checkbox" name="terms" required class="peer sr-only">
                        <div class="w-6 h-6 rounded-lg border-2 border-slate-700 bg-slate-900 peer-checked:bg-blue-600 peer-checked:border-blue-600 transition-all flex items-center justify-center shadow-inner">
                            <i class="fas fa-check text-white text-[10px] opacity-0 peer-checked:opacity-100 transition-opacity"></i>
                        </div>
                    </div>
                    <span class="text-xs text-slate-500 group-hover:text-slate-300 transition-colors leading-relaxed">
                        I agree to the <a href="index.php?module=info&page=terms" target="_blank" class="text-blue-500 font-bold hover:underline">Terms of Service</a> and <a href="index.php?module=info&page=privacy" target="_blank" class="text-blue-500 font-bold hover:underline">Privacy Policy</a>
                    </span>
                </label>

                <button type="submit" id="submitBtn" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-5 rounded-2xl shadow-lg shadow-blue-500/20 transition-all active:scale-[0.98] flex justify-center items-center gap-3 group/btn">
                    <span id="btnContent">Create My Account</span>
                    <i class="fas fa-arrow-right text-xs group-hover/btn:translate-x-1 transition-transform"></i>
                </button>
            </form>
        </div>

        <!-- Footer -->
        <div class="text-center space-y-6">
            <p class="text-sm text-slate-500">
                Already have an account? <a href="index.php?module=auth&page=login" class="text-blue-500 font-bold hover:underline">Sign in</a>
            </p>
            <a href="index.php" class="inline-flex items-center gap-2 text-xs font-bold text-slate-600 hover:text-white transition uppercase tracking-widest">
                <i class="fas fa-home"></i> Back to Home
            </a>
        </div>
    </div>

    <!-- JS Logic -->
    <script>
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');
        const form = document.getElementById('registerForm');
        const btn = document.getElementById('submitBtn');
        const btnContent = document.getElementById('btnContent');
        
        const reqLen = document.getElementById('len');
        const reqCap = document.getElementById('cap');
        const reqNum = document.getElementById('num');
        const bar1 = document.getElementById('bar1');
        const bar2 = document.getElementById('bar2');
        const bar3 = document.getElementById('bar3');
        const strText = document.getElementById('strengthText');

        if(passwordInput) {
            passwordInput.addEventListener('input', function() {
                const val = this.value;
                let score = 0;
                
                if(val.length >= 8) { reqLen.classList.replace('text-slate-500', 'text-green-400'); score++; } 
                else { reqLen.classList.replace('text-green-400', 'text-slate-500'); }

                if(/[A-Z]/.test(val)) { reqCap.classList.replace('text-slate-500', 'text-green-400'); score++; } 
                else { reqCap.classList.replace('text-green-400', 'text-slate-500'); }

                if(/[0-9]/.test(val)) { reqNum.classList.replace('text-slate-500', 'text-green-400'); score++; } 
                else { reqNum.classList.replace('text-green-400', 'text-slate-500'); }

                bar1.className = 'flex-1 rounded-full transition-colors duration-300 ' + (score >= 1 ? 'bg-red-500' : 'bg-slate-700');
                bar2.className = 'flex-1 rounded-full transition-colors duration-300 ' + (score >= 2 ? 'bg-yellow-400' : 'bg-slate-700');
                bar3.className = 'flex-1 rounded-full transition-colors duration-300 ' + (score === 3 ? 'bg-green-400 shadow-[0_0_10px_#4ade80]' : 'bg-slate-700');

                if(score === 0) { strText.innerText = 'Weak'; strText.className = 'text-[10px] font-bold uppercase tracking-widest text-slate-500'; }
                if(score === 1) { strText.innerText = 'Fair'; strText.className = 'text-[10px] font-bold uppercase tracking-widest text-red-400'; }
                if(score === 2) { strText.innerText = 'Good'; strText.className = 'text-[10px] font-bold uppercase tracking-widest text-yellow-400'; }
                if(score === 3) { strText.innerText = 'Strong'; strText.className = 'text-[10px] font-bold uppercase tracking-widest text-green-400'; }
            });
        }

        function setupToggle(btnId, inputId) {
            const btn = document.getElementById(btnId);
            const input = document.getElementById(inputId);
            if(btn && input) {
                btn.addEventListener('click', function() {
                    const icon = this.querySelector('i');
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash', 'text-[#00f0ff]');
                    } else {
                        input.type = 'password';
                        icon.classList.remove('fa-eye-slash', 'text-[#00f0ff]');
                        icon.classList.add('fa-eye');
                    }
                });
            }
        }
        setupToggle('togglePassword', 'password');
        setupToggle('toggleConfirmPassword', 'confirm_password');

        if(form) {
            form.addEventListener('submit', function(e) {
                const p1 = passwordInput.value;
                const p2 = confirmInput.value;
                
                if(p1 !== p2) {
                    alert("Security Protocol failed: Passwords do not match.");
                    e.preventDefault();
                    return false;
                }
                
                btn.disabled = true;
                btn.classList.add('opacity-80', 'cursor-not-allowed', 'loader-shimmer');
                btnContent.innerHTML = 'Deploying... <i class="fas fa-circle-notch fa-spin"></i>';
                return true;
            });
        }
    </script>
</body>
</html>