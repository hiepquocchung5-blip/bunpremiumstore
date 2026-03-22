<?php
// modules/auth/register.php
// PRODUCTION v4.0 - Form State Retention, Submit Lockout & Neon UX

// 1. Redirect if already logged in
if (is_logged_in()) {
    redirect('index.php');
}

// Include Mail Service
require_once 'includes/MailService.php';

$error = '';
$success = '';

// Form state retention variables
$form_name = '';
$form_user = '';
$form_email = '';
$form_phone = '';

// ==========================================================================
// SECURITY: RATE LIMITING (Brute Force Protection)
// ==========================================================================
if (!isset($_SESSION['reg_attempts'])) $_SESSION['reg_attempts'] = 0;
if (!isset($_SESSION['reg_lockout'])) $_SESSION['reg_lockout'] = 0;

// Block if too many attempts
if ($_SESSION['reg_attempts'] >= 3 && time() < $_SESSION['reg_lockout']) {
    $remaining = ceil(($_SESSION['reg_lockout'] - time()) / 60);
    $error = "System locked. Too many failed attempts. Please wait $remaining minutes.";
} 

// ==========================================================================
// HANDLE FORM SUBMISSION
// ==========================================================================
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Retain form data on error
    $form_name = trim($_POST['full_name'] ?? '');
    $form_user = trim($_POST['username'] ?? '');
    $form_email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $form_phone = trim($_POST['phone'] ?? '');
    
    // 2. SECURITY: CSRF Check
    $session_token = $_SESSION['csrf_token'] ?? '';
    $post_token = $_POST['csrf_token'] ?? '';

    // 3. SECURITY: Honeypot Trap (Anti-Bot)
    $honeypot = $_POST['fax'] ?? '';

    if (!empty($honeypot)) {
        // Silent failure for bots
        die("System error. Connection terminated.");
    }

    if (empty($session_token) || !hash_equals($session_token, $post_token)) {
        $error = "Security token expired. Please refresh the matrix.";
    } else {
        $password = $_POST['password'];
        $confirm = $_POST['confirm_password'];
        $terms = isset($_POST['terms']);

        // 5. SECURITY: Input Validation
        if (!filter_var($form_email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format detected.";
        } elseif (!$terms) {
            $error = "You must acknowledge the Security Protocols (Terms).";
        } elseif ($password !== $confirm) {
            $error = "Master Keys do not match.";
        } elseif (strlen($password) < 8) {
            $error = "Master Key must be at least 8 characters.";
        } elseif (!preg_match("/[A-Z]/", $password) || !preg_match("/[0-9]/", $password)) {
            $error = "Master Key must contain at least 1 Capital letter and 1 Number.";
        } else {
            // Force UTF-8MB4 connection for Emoji/Special char support
            $pdo->exec("SET NAMES 'utf8mb4'");

            // Check Database for duplicates
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$form_email, $form_user]);
            
            if ($stmt->rowCount() > 0) {
                $error = "Identity collision: Username or Email is already active in the network.";
            } else {
                try {
                    // 6. SECURITY: Password Hashing
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $token = bin2hex(random_bytes(32)); 
                    $phoneVal = empty($form_phone) ? null : $form_phone;

                    // 7. SECURITY: Prepared Statement
                    $stmt = $pdo->prepare("INSERT INTO users (full_name, username, email, phone, password, verify_token, is_verified) VALUES (?, ?, ?, ?, ?, ?, 0)");
                    
                    if ($stmt->execute([$form_name, $form_user, $form_email, $phoneVal, $hashed, $token])) {
                        
                        // Send Verification Email
                        try {
                            $mailer = new MailService();
                            $mailer->sendVerificationEmail($form_email, $token);
                        } catch(Exception $e) {
                            error_log("Failed to send verification email on registration: " . $e->getMessage());
                        }

                        $_SESSION['reg_attempts'] = 0;

                        // Redirect to Login
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
                        error_log($e->getMessage()); 
                        $error = "An unexpected error occurred in the matrix. Please try again.";
                    }
                }
            }
        }
        
        // Increment rate limit
        if ($error) {
            $_SESSION['reg_attempts']++;
            if ($_SESSION['reg_attempts'] >= 3) {
                $_SESSION['reg_lockout'] = time() + (10 * 60); // 10 min lock
            }
        }
    }
}

// Google Auth Link (Safe variable fetch)
$google_client_id = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '';
$google_redirect_url = defined('GOOGLE_REDIRECT_URL') ? GOOGLE_REDIRECT_URL : '';
$google_login_url = "https://accounts.google.com/o/oauth2/v2/auth?response_type=code&client_id=" . urlencode($google_client_id) . "&redirect_uri=" . urlencode($google_redirect_url) . "&scope=email%20profile";
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
        
        /* Mobile Optimized Inputs */
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
            font-size: 16px !important; /* Prevents iOS Zoom */
        }
        .input-field:focus + .input-icon { color: #00f0ff; }
        .input-field:focus { 
            border-color: #00f0ff; 
            box-shadow: inset 0 0 15px rgba(0, 240, 255, 0.15); 
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
        
        .visually-hidden { position: absolute; left: -9999px; top: -9999px; visibility: hidden; }

        /* Loader */
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
<body class="flex items-center justify-center relative overflow-x-hidden px-4 py-12 md:py-8 min-h-screen">
    
    <!-- Animated Cyberpunk Background -->
    <div class="fixed inset-0 w-full h-full bg-slate-950 -z-20"></div>
    <div class="fixed top-0 -left-4 w-72 h-72 bg-blue-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-30 animate-blob -z-10"></div>
    <div class="fixed top-0 -right-4 w-72 h-72 bg-[#00f0ff] rounded-full mix-blend-multiply filter blur-[128px] opacity-20 animate-blob animation-delay-2000 -z-10"></div>
    <div class="fixed -bottom-8 left-20 w-72 h-72 bg-purple-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-30 animate-blob animation-delay-4000 -z-10"></div>

    <!-- Main Container -->
    <div class="w-full max-w-xl glass p-6 md:p-10 rounded-3xl relative z-10 animate-fade-in-down border-t border-[#00f0ff]/30">
        
        <!-- Header -->
        <div class="text-center mb-8">
            <a href="index.php" class="inline-block mb-4 group">
                <div class="w-16 h-16 bg-slate-900 border border-[#00f0ff]/30 rounded-2xl flex items-center justify-center mx-auto shadow-[0_0_15px_rgba(0,240,255,0.2)] group-hover:shadow-[0_0_25px_rgba(0,240,255,0.4)] transition duration-300">
                    <i class="fas fa-satellite-dish text-3xl text-[#00f0ff]"></i>
                </div>
            </a>
            <h2 class="text-3xl font-black tracking-tight text-white mb-1">Initialize Operative</h2>
            <p class="text-slate-400 text-sm">Deploy your identity into the network</p>
        </div>
        
        <!-- Alerts -->
        <?php if($error): ?>
            <div class="bg-red-900/20 border border-red-500/50 text-red-400 p-4 rounded-xl mb-6 text-sm backdrop-blur-md shadow-lg flex items-start gap-3">
                <i class="fas fa-exclamation-triangle text-lg mt-0.5 shrink-0"></i>
                <span class="font-medium leading-snug"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Google Sign-Up -->
        <?php if(!empty($google_client_id)): ?>
        <a href="<?php echo $google_login_url; ?>" class="w-full bg-white hover:bg-gray-100 text-slate-900 font-black py-3.5 px-4 rounded-xl shadow-lg transition transform active:scale-[0.98] flex items-center justify-center gap-3 mb-6 group">
            <img src="https://www.svgrepo.com/show/475656/google-color.svg" class="w-6 h-6 transition-transform group-hover:scale-110" alt="Google">
            <span class="text-sm tracking-wide">Fast Deploy with Google</span>
        </a>

        <div class="flex items-center gap-4 mb-6">
            <div class="h-px bg-slate-700/80 flex-1"></div>
            <span class="text-[10px] text-slate-500 uppercase font-black tracking-widest">Or manual setup</span>
            <div class="h-px bg-slate-700/80 flex-1"></div>
        </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" id="registerForm" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <!-- Honeypot -->
            <input type="text" name="fax" class="visually-hidden" tabindex="-1" autocomplete="off">
            
            <!-- Row 1: Identity -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="input-group">
                    <input type="text" name="full_name" placeholder="Full Designation" required value="<?php echo htmlspecialchars($form_name); ?>"
                           class="input-field w-full bg-slate-900/60 border border-slate-600 rounded-xl py-3.5 text-white focus:border-[#00f0ff] outline-none placeholder-slate-500 backdrop-blur-sm shadow-inner">
                    <i class="fas fa-id-badge input-icon"></i>
                </div>
                <div class="input-group">
                    <input type="text" name="username" placeholder="Handle (Username)" required value="<?php echo htmlspecialchars($form_user); ?>"
                           class="input-field w-full bg-slate-900/60 border border-slate-600 rounded-xl py-3.5 text-white focus:border-[#00f0ff] outline-none placeholder-slate-500 backdrop-blur-sm shadow-inner">
                    <i class="fas fa-at input-icon"></i>
                </div>
            </div>

            <!-- Row 2: Comms -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="input-group">
                    <input type="email" name="email" placeholder="Secure Comm (Email)" required value="<?php echo htmlspecialchars($form_email); ?>"
                           class="input-field w-full bg-slate-900/60 border border-slate-600 rounded-xl py-3.5 text-white focus:border-[#00f0ff] outline-none placeholder-slate-500 backdrop-blur-sm shadow-inner">
                    <i class="fas fa-envelope input-icon"></i>
                </div>

                <div class="input-group">
                    <input type="tel" name="phone" placeholder="Local Link (Optional Phone)" value="<?php echo htmlspecialchars($form_phone); ?>"
                           class="input-field w-full bg-slate-900/60 border border-slate-600 rounded-xl py-3.5 text-white focus:border-[#00f0ff] outline-none placeholder-slate-500 backdrop-blur-sm shadow-inner">
                    <i class="fas fa-phone-alt input-icon text-sm"></i>
                </div>
            </div>

            <!-- Row 3: Security -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="input-group">
                    <input type="password" name="password" id="password" placeholder="Master Key" required 
                           class="input-field w-full bg-slate-900/60 border border-slate-600 rounded-xl py-3.5 text-white focus:border-[#00f0ff] outline-none placeholder-slate-500 backdrop-blur-sm pr-12 shadow-inner">
                    <i class="fas fa-key input-icon"></i>
                    <button type="button" id="togglePassword" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400 hover:text-[#00f0ff] transition-colors duration-200 focus:outline-none">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="input-group">
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Verify Key" required 
                           class="input-field w-full bg-slate-900/60 border border-slate-600 rounded-xl py-3.5 text-white focus:border-[#00f0ff] outline-none placeholder-slate-500 backdrop-blur-sm pr-12 shadow-inner">
                    <i class="fas fa-check-double input-icon"></i>
                    <button type="button" id="toggleConfirmPassword" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400 hover:text-[#00f0ff] transition-colors duration-200 focus:outline-none">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <!-- Security Requirements (Interactive) -->
            <div class="bg-slate-900/50 rounded-xl p-3 border border-slate-700 shadow-inner">
                <div class="flex justify-between items-center mb-2 px-1">
                    <span class="text-[10px] uppercase font-bold text-slate-500 tracking-widest">Key Strength</span>
                    <span id="strengthText" class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Weak</span>
                </div>
                <div class="flex gap-1 h-1.5 mb-3">
                    <div id="bar1" class="flex-1 rounded-full bg-slate-700 transition-colors duration-300"></div>
                    <div id="bar2" class="flex-1 rounded-full bg-slate-700 transition-colors duration-300"></div>
                    <div id="bar3" class="flex-1 rounded-full bg-slate-700 transition-colors duration-300"></div>
                </div>
                <div class="text-[10px] text-slate-500 flex flex-wrap justify-between gap-2 px-1 font-medium tracking-wide">
                    <span id="len" class="flex items-center gap-1 transition-colors"><i class="fas fa-circle text-[5px]"></i> 8+ Chars</span>
                    <span id="cap" class="flex items-center gap-1 transition-colors"><i class="fas fa-circle text-[5px]"></i> Uppercase</span>
                    <span id="num" class="flex items-center gap-1 transition-colors"><i class="fas fa-circle text-[5px]"></i> Number</span>
                </div>
            </div>

            <!-- Agreements -->
            <label class="flex items-start gap-3 cursor-pointer group mt-4 select-none px-1">
                <div class="relative flex items-center pt-0.5">
                    <input type="checkbox" name="terms" required class="peer sr-only">
                    <div class="w-5 h-5 rounded border border-slate-600 bg-slate-900 peer-checked:bg-[#00f0ff] peer-checked:border-[#00f0ff] transition flex items-center justify-center">
                        <i class="fas fa-check text-slate-900 text-xs opacity-0 peer-checked:opacity-100 transition transform scale-50 peer-checked:scale-100"></i>
                    </div>
                </div>
                <span class="text-xs text-slate-400 group-hover:text-white transition leading-snug">
                    I acknowledge the <a href="index.php?module=info&page=terms" target="_blank" class="text-[#00f0ff] font-bold hover:underline">Terms of Service</a> & <a href="index.php?module=info&page=privacy" target="_blank" class="text-[#00f0ff] font-bold hover:underline">Privacy Policy</a>
                </span>
            </label>

            <!-- Submit -->
            <button type="submit" id="submitBtn" class="w-full bg-gradient-to-r from-blue-600 to-[#00f0ff] hover:from-blue-500 hover:to-[#00f0ff] text-slate-900 font-black py-4 rounded-xl shadow-[0_0_20px_rgba(0,240,255,0.2)] hover:shadow-[0_0_30px_rgba(0,240,255,0.4)] transform transition active:scale-[0.98] text-sm uppercase tracking-widest mt-6 flex justify-center items-center gap-2 group/btn relative overflow-hidden">
                <span class="relative z-10 flex items-center gap-2" id="btnContent">
                    Deploy Credentials <i class="fas fa-upload group-hover/btn:-translate-y-1 transition-transform"></i>
                </span>
            </button>
        </form>

        <!-- Footer -->
        <div class="mt-8 pt-6 border-t border-slate-700/50 text-center">
            <p class="text-sm text-slate-400 font-medium">
                Already registered? <a href="index.php?module=auth&page=login" class="text-[#00f0ff] font-bold hover:underline ml-1">Initiate Login</a>
            </p>
        </div>
    </div>

    <!-- Interactive Script Logic -->
    <script>
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');
        const form = document.getElementById('registerForm');
        const btn = document.getElementById('submitBtn');
        const btnContent = document.getElementById('btnContent');
        
        // Strength Elements
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
                
                // Length check
                if(val.length >= 8) { 
                    reqLen.classList.replace('text-slate-500', 'text-green-400');
                    score++;
                } else { 
                    reqLen.classList.replace('text-green-400', 'text-slate-500'); 
                }

                // Capital check
                if(/[A-Z]/.test(val)) { 
                    reqCap.classList.replace('text-slate-500', 'text-green-400');
                    score++;
                } else { 
                    reqCap.classList.replace('text-green-400', 'text-slate-500'); 
                }

                // Number check
                if(/[0-9]/.test(val)) { 
                    reqNum.classList.replace('text-slate-500', 'text-green-400');
                    score++;
                } else { 
                    reqNum.classList.replace('text-green-400', 'text-slate-500'); 
                }

                // Visual Bars
                bar1.className = 'flex-1 rounded-full transition-colors duration-300 ' + (score >= 1 ? 'bg-red-500' : 'bg-slate-700');
                bar2.className = 'flex-1 rounded-full transition-colors duration-300 ' + (score >= 2 ? 'bg-yellow-400' : 'bg-slate-700');
                bar3.className = 'flex-1 rounded-full transition-colors duration-300 ' + (score === 3 ? 'bg-green-400 shadow-[0_0_10px_#4ade80]' : 'bg-slate-700');

                if(score === 0) { strText.innerText = 'Weak'; strText.className = 'text-[10px] font-bold uppercase tracking-widest text-slate-500'; }
                if(score === 1) { strText.innerText = 'Fair'; strText.className = 'text-[10px] font-bold uppercase tracking-widest text-red-400'; }
                if(score === 2) { strText.innerText = 'Good'; strText.className = 'text-[10px] font-bold uppercase tracking-widest text-yellow-400'; }
                if(score === 3) { strText.innerText = 'Strong'; strText.className = 'text-[10px] font-bold uppercase tracking-widest text-green-400'; }
            });
        }

        // Password visibility toggles
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

        // Form Submit Lockout & Validation
        if(form) {
            form.addEventListener('submit', function(e) {
                const p1 = passwordInput.value;
                const p2 = confirmInput.value;
                
                if(p1 !== p2) {
                    alert("Security Protocol failed: Master Keys do not match.");
                    e.preventDefault();
                    return false;
                }
                
                // UX: Lock button to prevent double submission
                btn.disabled = true;
                btn.classList.add('opacity-80', 'cursor-not-allowed', 'loader-shimmer');
                btnContent.innerHTML = 'Deploying... <i class="fas fa-circle-notch fa-spin"></i>';
                
                // Allow form to submit naturally
                return true;
            });
        }
    </script>
</body>
</html>